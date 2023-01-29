<?php

namespace Tests\Unit;

use App\Jobs\ProcessJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use App\Exceptions\RedisConnectionException;

class ProcessJsonTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    /**
     * Test if the json file is read and written to the database correctly
     *
     * @return void
     */
    public function testProcessJson()
    {
        Queue::fake();
        Artisan::call('queue:work', ['--once' => true]);
        Queue::assertPushed(ProcessJson::class);
        $this->assertDatabaseHas('applications', [
            'account' => '556436171909',
            'name' => 'Prof. Simeon Green',
            'address' => '328 Bergstrom Heights Suite 709 49592 Lake Allenville',
            'email' => 'nerdman@cormier.net'
        ]);
        $this->assertDatabaseHas('credit_cards', [
            'number' => '4532383564703',
            'name' => 'Brooks Hudson',
            'expirationDate' => '12/19',
            'type' => 'Visa'
        ]);
    }

    /**
     * Test the age validation method of the ProcessJson class
     *
     * @return void
     */
    public function testAgeValidation()
    {
        $processJson = new ProcessJson(storage_path('app/challenge.json'));
        // Test with valid age
        $validAge = 18;
        $this->assertTrue($processJson->validAge($validAge));

        // Test with invalid age
        $invalidAge = 12;
        $this->assertFalse($processJson->validAge($invalidAge));

        // Test with age above 65
        $ageAbove65 = 70;
        $this->assertFalse($processJson->validAge($ageAbove65));

        // Test with unknown age
        $unknownAge = null;
        $this->assertTrue($processJson->validAge($unknownAge));
    }

    /**
     * Test credit card number validation.
     *
     * @return void
     */
    public function testValidCreditCardNumber()
    {
        $processJson = new ProcessJson('');

        // Test a valid credit card number
        $validCreditCardNumber = '1234123412341234';
        $this->assertTrue($processJson->validCreditCardNumber($validCreditCardNumber));

        // Test a credit card number with 3 consecutive digits
        $invalidCreditCardNumber = '1234123412341233';
        $this->assertFalse($processJson->validCreditCardNumber($invalidCreditCardNumber));

        // Test a credit card number with less than 16 digits
        $invalidCreditCardNumber = '123412341234123';
        $this->assertFalse($processJson->validCreditCardNumber($invalidCreditCardNumber));

        // Test a credit card number with more than 16 digits
        $invalidCreditCardNumber = '123412341234123412';
        $this->assertFalse($processJson->validCreditCardNumber($invalidCreditCardNumber));
    }

    /**
     * Test the handle method with a valid JSON file.
     *
     * @return void
     */
    public function testHandleWithValidJson()
    {
        // Create a mock JSON file
        $tempFile = storage_path('app/challenge.json');
        file_put_contents($tempFile, '{"name":"John Doe","address":"123 Main St","checked":true,"description":"A description","interest":0.12,"date_of_birth":"2000-01-01","email":"johndoe@example.com","account":"12345678","credit_card":{"type":"visa","number":"1234567812345678","name":"John Doe","expirationDate":"2022-01-01"}}');

        // Dispatch the job and run the handle method
        $processJson = new ProcessJson($tempFile);
        $processJson->handle();

        // Assert that the application was created in the database
        $this->assertDatabaseHas('applications', ['name' => 'John Doe', 'address' => '123 Main St']);

        // Assert that the credit card was created in the database
        $this->assertDatabaseHas('credit_cards', ['number' => '1234567812345678']);

        // Remove the mock JSON file
        unlink($tempFile);
    }

    /**
     * Test the handle method with a valid XML file
     *
     * @return void
     */
    public function testHandleMethodWithValidXmlFile()
    {

        $file = storage_path('app/valid_file.xml');
        $job = new ProcessJson($file);

        // Act
        $job->handle();

        // Assert
        $this->assertDatabaseHas('applications', [
            'account' => '1234567890'
        ]);
    }

    /**
     * Test the handle method with a valid CSV file
     *
     * @return void
     */
    public function testHandleMethodWithValidCsvFile()
    {
        // Arrange
        $file = storage_path('app/valid_file.csv');
        $job = new ProcessJson($file);

        // Act
        $job->handle();

        // Assert
        $this->assertDatabaseHas('applications', [
            'account' => '0987654321'
        ]);
    }

    /**
     * Test the handle method with an invalid file type
     *
     * @return void
     */
    public function testHandleMethodWithInvalidFileType()
    {
        $this->expectException(\Exception::class);

        // Arrange
        $file = storage_path('app/invalid_file.txt');
        $job = new ProcessJson($file);

        // Act
        $job->handle();
    }

    /**
     * Test the handle method when Redis connection fails
     *
     * @return void
     */
    public function testHandleMethodWhenRedisConnectionFails()
    {
        $this->expectException(RedisConnectionException::class);

        Redis::shouldReceive('get')
            ->andThrow(RedisConnectionException::class);

        // Arrange
        $file = storage_path('app/valid_file.json');
        $job = new ProcessJson($file);

        // Act
        $job->handle();
    }
}
