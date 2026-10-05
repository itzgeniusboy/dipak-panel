<?php
date_default_timezone_set("Asia/Kolkata");
// Database connection parameters
$host = 'localhost:3306';
$dbname = 'multipenel_dipak';
$username = 'multipenel_dipak'; // Replace with your database username
$password = 'multipenel_dipak'; // Replace with your database password

try {
    // Create a PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Calculate the timestamp for 1 hour ago (changed from 2 hours)
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    // First, let's check which keys will be deleted (for logging)
    $checkSql = "SELECT id_keys, user_key, created_at 
                 FROM keys_code 
                 WHERE expired_date IS NULL 
                 AND created_at <= :oneHourAgo";
    
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':oneHourAgo', $oneHourAgo, PDO::PARAM_STR);
    $checkStmt->execute();
    $keysToDelete = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // SQL query to delete keys without expired_date that are older than 1 hour
    $deleteSql = "DELETE FROM keys_code 
                  WHERE expired_date IS NULL 
                  AND created_at <= :oneHourAgo";
    
    // Prepare the statement
    $deleteStmt = $pdo->prepare($deleteSql);
    
    // Bind the parameter
    $deleteStmt->bindParam(':oneHourAgo', $oneHourAgo, PDO::PARAM_STR);
    
    // Execute the query
    $result = $deleteStmt->execute();
    
    // Check how many rows were affected
    $deletedCount = $deleteStmt->rowCount();
    
    // Log the results
    echo "Checked time: " . date('Y-m-d H:i:s') . "\n";
    echo "Time threshold: $oneHourAgo\n";
    echo "Keys found to delete: " . count($keysToDelete) . "\n";
    
    if (count($keysToDelete) > 0) {
        echo "Keys to be deleted:\n";
        foreach ($keysToDelete as $key) {
            echo "- ID: " . $key['id_keys'] . ", Key: " . $key['user_key'] . ", Created: " . $key['created_at'] . "\n";
        }
    }
    
    echo "Successfully deleted $deletedCount keys that were created more than 1 hour ago and have no expiration date.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Close the connection
$pdo = null;
?>