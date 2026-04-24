<?php
$directory = 'C:\\xampp\\htdocs\\web-engineering-project\\';

function fixIncludes($dir) {
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $path = $dir . $file;
        
        if (is_dir($path)) {
            fixIncludes($path . '\\');
        } elseif (pathinfo($path, PATHINFO_EXTENSION) == 'php') {
            $content = file_get_contents($path);
            if (strpos($content, "../includes/auth.php") !== false) {
                $newcontent = str_replace("../includes/auth.php", "../includes/functions.php", $content);
                file_put_contents($path, $newcontent);
                echo "Fixed: " . $path . "<br>";
            }
        }
    }
}

fixIncludes($directory);
echo "<br>Done! All files updated.";
?>