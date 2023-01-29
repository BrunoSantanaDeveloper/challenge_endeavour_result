
# Endeavour Challenge Result

### Description:
As a solution to the Endeavour challenge, this PHP script is responsible for processing a data file of applications (in JSON, XML or CSV format) and inserting the relevant information into a database.

The "ProcessJson" job uses the resources of "Queueable", "Dispatchable", "InteractsWithQueue", and "SerializesModels" from Laravel, allowing the job to be added to a queue and processed asynchronously. This is important to ensure that processing large files does not negatively impact the performance of the application.

The class constructor receives the path to the file to be processed and determines the file type (JSON, XML, or CSV) through the PHP "pathinfo" function. The "handle" function is then called to start processing.

The script uses Redis to store the last processed application index, allowing processing to be interrupted and resumed without processing the same records again. If there is a failure in the connection with Redis, the job is delayed for 5 minutes before being reexecuted.

The file data is read and divided into chunks of 100 applications to avoid memory overload. Each chunk is then processed in a database transaction to ensure consistency. Ages and credit card numbers are validated before being inserted into the database.

Exceptions are handled to ensure that processing can continue even when there are problems, such as missing files or Redis connection errors. Exceptions are logged for debugging purposes.
