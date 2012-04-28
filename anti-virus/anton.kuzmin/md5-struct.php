<?php
/*
 * Скрипт для построения и сверки сигнатур структур сайтов.
 * Перед использованием обязательно измените первые переменные ($myMail, $ignoreRegexprs, $sendOnOk)
 * Запуск: php md5-struct.php /dir/of/site /path/to/struct/file build|check
 * Автор: Антон Кузьмин | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com
 */

// Почтовый адрес для отчётов
$myMail = "aaa@bbb.ru"; 

// Массив с регулярными выражениями для игнорирования отдельных файлов или их групп
$ignoreRegexprs = array("#(.*)art\.odt$#", "#(.*)u\.php$#");

// Сообщать ли при отсутствии нарушений (письмо с фразой ОК)
$sendOnOk = true; 


if(!isset($argv[3]))
    die(help());

$dirOfSite        = $argv[1];
$pathToStructFile = $argv[2];
$cmd              = $argv[3];


if(!file_exists($dirOfSite)) die("Directory $dirOfSite not exists\n");

if($cmd == 'build') {
    if(!is_writable(dirname($pathToStructFile))) die(dirname($pathToStructFile) . " is not writable\n");
    exec("find $dirOfSite -type f -exec md5sum {} \; > $pathToStructFile");
} else {
    if(!file_exists($pathToStructFile)) die("File with signature not exists\n");
    if(!is_readable($pathToStructFile)) die("File with signature not readable\n");
    
    exec("find $dirOfSite -type f -exec md5sum {} \; > {$pathToStructFile}tmp");

    $log = file_get_contents($pathToStructFile);
    $checkLog = file_get_contents($pathToStructFile . "tmp");

    $log = makeArrFromList($log);
    $checkLog = makeArrFromList($checkLog);

    $message = "";
    foreach($log as $file => $summ) {

        foreach($ignoreRegexprs as $expr) {
            if(preg_match($expr, $file)) {
                unset($log[$file], $checkLog[$file]);
                $file = false;
                break;
            }
        }    

        if($file AND isset($checkLog[$file])) {
            if($checkLog[$file] !== $summ)
                $message .= "$file\n";
            unset($checkLog[$file], $log[$file]);
        }
    
    }

    if(count($log)) {
        $message .= "\nDeleted:\n";
        foreach($log as $k => $v)
            $message .= "$k\n";
    }
    
    if(count($checkLog)) {
        $message .= "\nNew:\n";
        foreach($checkLog as $k => $v)
            $message .= "$k\n";
    }

    if(strlen($message))
        mail($myMail, "ALERT", $message);
    elseif($sendOnOk) 
        mail($myMail, "OK", "OK");
    
}



function makeArrFromList($list) {
    $result = array();
    
    $list = explode("\n", $list);
    $list = array_map('trim', $list);
    foreach($list as $str) {
        if($str) {
            $str = explode("  ", $str);
            $result[$str[1]] = $str[0];
        }
    }        
    
    return $result;
}

function help() {
    print "================================================================================\n" . 
          "Script for build and check structure signatures of web-sites.\n" .
          "Please, change source code (first strings) before use.\n" .
          "Usage: php md5-struct.php /dir/of/site /path/to/struct/file build|check\n" .
          "(c) Anton Kuzmin | anton.kuzmin.russia@gmail.com | http://anton-kuzmin.blogspot.com/\n" .
          "================================================================================\n";
}

