<?php
require_once "libs/pdfparser-2.2.1/alt_autoload.php-dist";

`gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -sOutputFile=unencrypted.pdf -c .setpdfwrite -f SSR_TSRPT.pdf`;

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile('unencrypted.pdf');

$text = $pdf->getText();

$text = preg_replace("/[ \t]+/", " ", $text);
$semesters = explode("Description Attempted Earned Grade Points ", $text);

$block_num = -1;
foreach ($semesters as $semester) {
    $block_num++;

    if ($block_num == 0) {
        continue;
    }
    $courses = explode("\n", $semester);
    echo var_dump($courses);
    foreach ($courses as $course) {
        if ($course == " Attempted ") {
            break;
        }
        echo "string: " . $course . "<br>";
        preg_match("/(\b[\S]{2,5} [\d]{2,5}\b) ([\S\s ]+) [\d.]+ [\d.]+ ([\w-+]+) /", $course, $match);
        echo "Course Code: " . $match[1] . "<br>";
        echo "Course Title: " . $match[2] . "<br>";
        echo "Course Grade: " . $match[3] . "<br>";
        echo "<br>";
    }
}
    
?>