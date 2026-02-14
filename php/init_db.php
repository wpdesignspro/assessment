<?php
require_once __DIR__ . '/config/database.php';

try {
    // Read the SQL from the create_table file and execute it
    $sql = file_get_contents(__DIR__ . '/create_table.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read the SQL file.");
    }
    
    $pdo->exec($sql);
    
    echo "Database table created successfully!";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}