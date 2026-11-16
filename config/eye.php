<?php

declare(strict_types=1);

// The eye on the home page: the open frame first, then the blink, each shown for "duration" milliseconds;
// "closed" is the frame with the eye shut, where no iris is drawn.
return [
    ["file" => "frame-01.txt", "duration" => 2200],
    ["file" => "frame-02.txt", "duration" => 40],
    ["file" => "frame-03.txt", "duration" => 40],
    ["file" => "frame-04.txt", "duration" => 40],
    ["file" => "frame-05.txt", "duration" => 100, "closed" => true],
    ["file" => "frame-06.txt", "duration" => 50],
    ["file" => "frame-07.txt", "duration" => 50],
    ["file" => "frame-08.txt", "duration" => 50],
];
