<?php

namespace x {
    function markdown($content) {
        $type = $this->type;
        if ('Markdown' !== $type && 'text/markdown' !== $type) {
            return $content;
        }
        $out = new \ParsedownExtraPlugin;
        foreach (\State::get('x.markdown', true) ?? [] as $k => $v) {
            $out->{$k} = $v;
        }
        return "" !== ($out = $out->text($content ?? "")) ? $out : null;
    }
    \Hook::set([
        'page.content'
    ], __NAMESPACE__ . "\\markdown", 2);
}

namespace x\markdown {
    function span($content) { // Inline tag(s) only
        $type = $this->type;
        if ('Markdown' !== $type && 'text/markdown' !== $type) {
            return $content;
        }
        $out = new \ParsedownExtraPlugin;
        foreach (\State::get('x.markdown', true) ?? [] as $k => $v) {
            if (0 === \strpos($k, 'block')) {
                continue;
            }
            $out->{$k} = $v;
        }
        return "" !== ($out = $out->line($content ?? "")) ? $out : null;
    }
    \Hook::set([
        'page.description',
        'page.title'
    ], __NAMESPACE__ . "\\span", 2);
}