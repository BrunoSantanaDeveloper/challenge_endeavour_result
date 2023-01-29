<?php

namespace App\Jobs;

use App\Exceptions\RedisConnectionException;
use App\Models\Application;
use App\Models\CreditCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Services\ValidAge;
use App\Services\ValidCreditCardNumber;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;

class ProcessJson implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    private $fileType;

    /**
     * @var string
     */
    private $file;

    /**
     * ProcessJson constructor.
     *
     * @param string $file
     */
    public function __construct(string $file)
    {
        $this->fileType = pathinfo($file, PATHINFO_EXTENSION);
        $this->file = $file;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Get the last processed index from Redis
            $lastProcessedIndex = Redis::get('last_processed_index') ?: 0;
            // Read the file based on its type
            $data = $this->readFile();

            // Process the data in chunks of 100
            foreach (array_chunk($data, 100) as $index => $chunk) {
                //skip the chunks that have already been processed
                if ($index < $lastProcessedIndex) {
                    continue;
                }
                // Start a database transaction
                DB::transaction(function () use ($chunk) {
                    // Loop through each application in the chunk
                    foreach ($chunk as $application) {
                        // Check if the age and credit card number are valid
                        if ($this->validAge($application->age) && $this->validCreditCardNumber($application->credit_card->number)) {
                            // Create the application and credit card records
                            $app = Application::create(
                                [
                                    'account' => $application->account
                                ],
                                [
                                    'name' => $application->name,
                                    'address' => $application->address,
                                    'checked' => $application->checked,
                                    'description' => $application->description,
                                    'interest' => $application->interest,
                                    'date_of_birth' => $application->date_of_birth,
                                    'email' => $application->email,
                                ]
                            );
                            $creditCard = new CreditCard(
                                [
                                    'number' => $application->credit_card->number
                                ],
                                [
                                    'type' => $application->credit_card->type,
                                    'name' => $application->credit_card->name,
                                    'expirationDate' => $application->credit_card->expirationDate,
                                ]
                            );
                            $app->creditCard()->save($creditCard);
                        }
                    }
                });
                //check if the transaction was successful
                if (!DB::transactionStatus()) {
                    Redis::set('last_processed_index', $index);
                }
            }
        } catch (\Exception $e) {
            // Log the exception
            if ($e instanceof FileNotFoundException) {
                Log::error('File not found: ' . $e->getMessage());
                return;
            } elseif ($e instanceof RedisConnectionException) {
                Log::error('Error connecting to Redis: ' . $e->getMessage());
                // retry the job in a few minutes
                ProcessJson::dispatch($this->file)->delay(now()->addMinutes(5));
                return;
            }
            Log::error('An error occurred: ' . $e->getMessage());
            return;
        }
    }



    /**
     * Read the file based on its type
     *
     * @return array
     */
    private function readFile()
    {
        switch ($this->fileType) {
            case 'json':
                $data = json_decode(file_get_contents($this->file));
                break;
            case 'xml':
                $xml = simplexml_load_file($this->file);
                $data = json_decode(json_encode($xml));
                break;
            case 'csv':
                $csv = array_map('str_getcsv', file($this->file));
                array_walk(
                    $csv,
                    function (&$a) use ($csv) {
                        $a = array_combine($csv[0], $a);
                    }
                );
                array_shift($csv);
                $data = json_decode(json_encode($csv));
                break;
            default:
                $data = [];
                break;
        }
        return $data;
    }


    /**
     * Filters the records based on age
     *
     * @param integer|string $age The age of the applicant
     * @return boolean
     */
    public function validAge($age)
    {
        $validAge = new ValidAge();
        return $validAge->isValid($age);
    }

    /**
     * Filters the records based on credit card number
     *
     * @param string $number The credit card number
     * @return boolean
     */
    public function validCreditCardNumber($number)
    {
        $validCreditCardNumber = new ValidCreditCardNumber();
        return $validCreditCardNumber->isValid($number);
    }
}
