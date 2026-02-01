<?php

namespace x\page\from\x {
    function markdown($content) {
        return md($content);
    }
    function md($content) {
        return txt($content);
    }
    function mkd($content) {
        return md($content);
    }
}

namespace x\page\to\x {
    function markdown($content) {
        return md($content);
    }
    function md($content) {
        return txt($content);
    }
    function mkd($content) {
        return md($content);
    }
}