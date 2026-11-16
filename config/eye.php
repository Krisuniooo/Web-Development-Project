<?php

declare(strict_types=1);

// The frames of the eye on the home page, from config/eye/, with how long each shows in milliseconds:
// the open eye first, then the blink; "closed" marks the frame with the eye shut, where the iris is not drawn.
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
