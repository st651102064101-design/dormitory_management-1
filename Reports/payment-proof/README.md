# Payment Proofs

Drop image files (JPEG, PNG, PDF, etc.) that correspond to the `pay_proof` field in `payment` records inside this folder. The filename stored in the database should match the file name placed in this folder so that `Reports/report_payments.php` can link to it.

If you need to store uploads under subfolders, update the report and database values accordingly. Ensure the web server has read access to this directory.
