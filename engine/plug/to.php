<?php

// The functionality to convert HTML to Markdown does exist in my Markdown converter project, but I am pretty sure that
// it will be used very rarely, so I decided to leave this method blank to reduce the file size and frequency of code
// maintenance. You are free to implement this reverse functionality for your own specific purpose(s), but for this core
// extension… I’m just going to keep the feature limited.
//
// <https://github.com/taufik-nurrohman/markdown>

To::_('markdown', function (?string $value, $block = true): ?string {});