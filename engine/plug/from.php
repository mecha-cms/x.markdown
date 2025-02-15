<?php

namespace x\markdown {
    function from(?string $value, $block = true): ?string {
        if (!$block) {
            [$row] = from\row($value);
            if (!$row) {
                return null;
            }
            if (\is_string($row)) {
                $value = \trim(\preg_replace('/\s+/', ' ', $row));
                return "" !== $value ? $value : null;
            }
            foreach ($row as &$v) {
                $v = \is_array($v) ? from\s($v) : $v;
            }
            $value = \trim(\preg_replace('/\s+/', ' ', \implode("", $row)));
            return "" !== $value ? $value : null;
        }
        [$rows] = from\rows($value);
        if (!$rows) {
            return null;
        }
        foreach ($rows as &$row) {
            $row = \is_array($row) ? from\s($row) : $row;
        }
        if ("" === ($value = \implode("", $rows))) {
            return null;
        }
        return \strtr($value, ['</dl><dl>' => ""]);
    }
    \From::_('markdown', __NAMESPACE__ . "\\from");
}

namespace x\markdown\from {
    function a(?string $info, $raw = false) {
        if ("" === ($info = \trim($info ?? ""))) {
            return $raw ? [] : null;
        }
        $a = [];
        $class = [];
        $id = null;
        if ('{' === $info[0] && '}' === \substr($info, -1)) {
            if ("" === ($info = \trim(\substr($info, 1, -1)))) {
                return $raw ? [] : null;
            }
            $pattern = '/([#.](?>\\\\.|[\w:-])+|(?>[\w:.-]+(?>=(?>' . q('"') . '|' . q("'") . '|\S+)?)?))/';
            foreach (\preg_split($pattern, $info, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
                if ("" === \trim($v)) {
                    continue; // Skip the space(s)
                }
                // `{#a}`
                if ('#' === $v[0]) {
                    $id = $id ?? \substr($v, 1);
                    continue;
                }
                // `{.a}`
                if ('.' === $v[0]) {
                    $class[] = \substr($v, 1);
                    continue;
                }
                // `{a=b}`
                if (false !== \strpos($v, '=')) {
                    $v = \explode('=', $v, 2);
                    // `{a=}`
                    if ("" === $v[1]) {
                        $a[$v[0]] = "";
                        continue;
                    }
                    // `{a="b"}` or `{a='b'}`
                    if ('"' === $v[1][0] || "'" === $v[1][0]) {
                        $v[1] = v(\substr($v[1], 1, -1));
                    // `{a=false}`
                    } else if ('false' === $v[1]) {
                        $v[1] = false;
                    // `{a=true}`
                    } else if ('true' === $v[1]) {
                        $v[1] = true;
                    // `{a=null}`
                    } else if ('null' === $v[1]) {
                        $v[1] = null;
                    }
                    if ('class' === $v[0]) {
                        $class[] = $v[1]; // Merge class value(s)
                        continue;
                    }
                    $a[$v[0]] = $v[1];
                    continue;
                }
                // `{a}`
                $a[$v] = true;
            }
            if ($class = \array_unique($class)) {
                \sort($class);
                $a['class'] = \implode(' ', $class);
            }
            if ($id) {
                $a['id'] = $id;
            }
            $a && \ksort($a);
            if ($raw) {
                return $a;
            }
            $out = [];
            foreach ($a as $k => $v) {
                $out[] = true === $v ? $k : $k . '="' . e($v) . '"';
            }
            return $out ? ' ' . \implode(' ', $out) : null;
        }
        foreach (\preg_split('/\s+|(?=[#.])/', $info, -1, \PREG_SPLIT_NO_EMPTY) as $v) {
            if ('#' === $v[0]) {
                $id = $id ?? \substr($v, 1);
                continue;
            }
            if ('.' === $v[0]) {
                $class[] = \substr($v, 1);
                continue;
            }
            $class[] = 'language-' . $v;
        }
        if ($class = \array_unique($class)) {
            \sort($class);
            $a['class'] = \implode(' ', $class);
        }
        if ($id) {
            $a['id'] = $id;
        }
        $a && \ksort($a);
        if ($raw) {
            return $a;
        }
        $out = [];
        foreach ($a as $k => $v) {
            $out[] = $k . '="' . e($v) . '"';
        }
        if ($out) {
            \sort($out);
            return ' ' . \implode(' ', $out);
        }
        return null;
    }
    function abbr(string $row, array &$lot = []) {
        // Optimize if current row is an abbreviation
        if (isset($lot[1][$row])) {
            $title = $lot[1][$row];
            return [['abbr', e($row), ['title' => "" !== $title ? $title : null], -1]];
        }
        // Else, chunk current row by abbreviation
        $abbr = [];
        if (!empty($lot[1])) {
            foreach ($lot[1] as $k => $v) {
                $abbr[] = \preg_quote($k, '/');
            }
        }
        if ($abbr) {
            $chops = [];
            foreach (\preg_split('/\b(' . \implode('|', $abbr) . ')\b/', $row, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $v) {
                if (isset($lot[1][$v])) {
                    $title = $lot[1][$v];
                    $chops[] = ['abbr', e($v), ['title' => "" !== $title ? $title : null], -1];
                    continue;
                }
                $chops[] = e($v);
            }
            return $chops;
        }
        return $row;
    }
    function d(?string $v, $as = \ENT_HTML5 | \ENT_QUOTES) {
        return \html_entity_decode($v ?? "", $as, 'UTF-8');
    }
    function e(?string $v, $as = \ENT_HTML5 | \ENT_QUOTES) {
        return \htmlspecialchars($v ?? "", $as, 'UTF-8');
    }
    function l(?string $link) {
        // ``
        if (!$link) {
            return true;
        }
        // `asdf` or `../asdf` or `/asdf` or `?asdf` or `#asdf`
        if (0 !== \strpos($link, '//') && (false === \strpos($link, '://') || false !== \strpos('./?#', $link[0]))) {
            return true;
        }
        // `//127.0.0.1` or `*://127.0.0.1`
        if (\parse_url($link, \PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? 0)) {
            return true;
        }
        return false;
    }
    function m($row) {
        if (!$row || !\is_array($row)) {
            return $row;
        }
        if (1 === ($count = \count($row))) {
            if (\is_string($v = \reset($row))) {
                return $v;
            }
            if (\is_array($v) && false === $v[0] && \is_string($v[1])) {
                return $v[1];
            }
        }
        // Concatenate a series of string(s) into one string
        foreach ($row = \array_values($row) as $k => $v) {
            if (\is_string($row[$k - 1] ?? 0) && \is_string($v)) {
                $row[$k - 1] .= $v;
                unset($row[$k]);
                continue;
            }
        }
        if (1 === \count($row = \array_values($row))) {
            if (\is_string($v = \reset($row))) {
                return $v;
            }
            if (\is_array($v) && false === $v[0] && \is_string($v[1])) {
                return $v[1];
            }
        }
        return $row;
    }
    function of(?string $row): array {
        if ("" === ($row ?? "")) {
            return [null, $row, [], 0];
        }
        $dent = \strspn($row, ' ');
        if ($dent >= 4) {
            return ['pre', \substr($row, 4), [], $dent, [1]];
        }
        // Remove indent(s)
        $row = \substr($row, $dent);
        if ("" === $row) {
            return [null, $row, [], $dent];
        }
        if (false !== \strpos($row, '|')) {
            return ['table', $row, [], $dent];
        }
        // `!…`
        if (0 === \strpos($row, '!')) {
            if (
                // `![asdf](…`
                \strpos($row, '](') > 0 ||
                // `![asdf][…`
                \strpos($row, '][') > 0 ||
                // `![asdf]` not followed by a `:`
                false !== ($n = \strpos($row, ']')) && ':' !== \substr($row, $n + 1, 1)
            ) {
                // `…)` or `…]` or `…}`
                if (false !== \strpos(')]}', \substr($row, -1))) {
                    return ['figure', $row, [], $dent];
                }
            }
            return ['p', $row, [], $dent];
        }
        // `#…`
        if (0 === \strpos($row, '#')) {
            $n = \strspn($row, '#');
            // `#######…`
            if ($n > 6) {
                return ['p', $row, [], $dent];
            }
            // `# …`
            if ($n === \strlen($row) || ' ' === \substr($row, $n, 1)) {
                return ['h' . $n, $row, [], $dent, ['#', $n]];
            }
            return ['p', $row, [], $dent];
        }
        // `*…`
        if ('*' === \rtrim($row)) {
            return ['ul', $row, [], $dent, [$row[0], 2]];
        }
        if (0 === \strpos($row, '*')) {
            // `*[…`
            if (1 === \strpos($row, '[') && false === \strpos($row, '](') && false === \strpos($row, '][')) {
                return [1, $row, [], $dent];
            }
            // `***`
            $test = \strtr($row, [' ' => ""]);
            if (\strspn($test, '*') === ($n = \strlen($test)) && $n > 2) {
                return ['hr', $row, [], $dent, ['*', $n]];
            }
            // `* …`
            if (' ' === \substr($row, 1, 1)) {
                $r = \strspn($row, ' ', 1);
                return ['ul', $row, [], $dent, [$row[0], 1 + ($r > 3 ? 3 : $r)]];
            }
            return ['p', $row, [], $dent];
        }
        // `+`
        if ('+' === \rtrim($row)) {
            return ['ul', $row, [], $dent, [$row[0], 2]];
        }
        // `+…`
        if (0 === \strpos($row, '+')) {
            // `+ …`
            if (' ' === \substr($row, 1, 1)) {
                $r = \strspn($row, ' ', 1);
                return ['ul', $row, [], $dent, [$row[0], 1 + ($r > 3 ? 3 : $r)]];
            }
            return ['p', $row, [], $dent];
        }
        // `-`
        if ('-' === \rtrim($row)) {
            return ['ul', $row, [], $dent, [$row[0], 2]];
        }
        // `--`
        if ('--' === \rtrim($row)) {
            return ['h2', $row, [], $dent, ['-', 2]]; // Look like a Setext-header level 2
        }
        // `-…`
        if (0 === \strpos($row, '-')) {
            // `---`
            $test = \strtr($row, [' ' => ""]);
            if (\strspn($test, '-') === ($n = \strlen($test)) && $n > 2) {
                return ['hr', $row, [], $dent, ['-', $n]];
            }
            // `- …`
            if (' ' === \substr($row, 1, 1)) {
                $r = \strspn($row, ' ', 1);
                return ['ul', $row, [], $dent, [$row[0], 1 + ($r > 3 ? 3 : $r)]];
            }
            return ['p', $row, [], $dent];
        }
        // `:…`
        if (0 === \strpos($row, ':')) {
            // `: …`
            if (' ' === \substr($row, 1, 1)) {
                return ['dl', $row, [], $dent, [$row[0], 1 + \strspn($row, ' ', 1)]]; // Look like a definition list
            }
            return ['p', $row, [], $dent];
        }
        // `<…`
        if (0 === \strpos($row, '<') && ' ' !== ($row[1] ?? 0)) {
            // <https://spec.commonmark.org/0.31.2#html-blocks>
            if ($t = \rtrim(\strtok(\substr($row, 1), " \n>"), '/')) {
                // `<!--…`
                if (0 === \strpos($t, '!--')) {
                    return [false, $row, [], $dent, [2, \substr($t, 0, 3)]]; // `!--asdf` → `!--`
                }
                // `<![CDATA[…`
                if (0 === \strpos($t, '![CDATA[')) {
                    return [false, $row, [], $dent, [5, \substr($t, 0, 8)]]; // `![CDATA[asdf` → `![CDATA[`
                }
                if ('!' === $t[0]) {
                    return \preg_match('/^[a-z]/i', \substr($t, 1)) ? [false, $row, [], $dent, [4, $t]] : ['p', $row, [], $dent];
                }
                if ('?' === $t[0]) {
                    return [false, $row, [], $dent, [3, '?' . \trim($t, '?')]];
                }
                // The `:` and `@` character is not a valid part of a HTML element name, so it must be a link syntax
                // <https://spec.commonmark.org/0.30#tag-name>
                if (false !== \strpos($t, ':') || false !== \strpos($t, '@')) {
                    return ['p', $row, [], $dent];
                }
                if (false !== \stripos(',pre,script,style,textarea,', ',' . \trim($t, '/') . ',')) {
                    return [false, $row, [], $dent, [1, $t]];
                }
                if (false !== \stripos(',address,article,aside,base,basefont,blockquote,body,caption,center,col,colgroup,dd,details,dialog,dir,div,dl,dt,fieldset,figcaption,figure,footer,form,frame,frameset,h1,h2,h3,h4,h5,h6,head,header,hr,html,iframe,legend,li,link,main,menu,menuitem,nav,noframes,ol,optgroup,option,p,param,search,section,source,summary,table,tbody,td,tfoot,th,thead,title,tr,track,ul,', ',' . \trim($t, '/') . ',')) {
                    return [false, $row, [], $dent, [6, $t]];
                }
                // <https://spec.commonmark.org/0.31.2#example-163>
                if ('>' === \substr($test = \rtrim($row), -1)) {
                    // <https://spec.commonmark.org/0.31.2#closing-tag>
                    if ('/' === $test[1] && \preg_match('/^<\/[a-z][a-z\d-]*\s*>$/i', $test)) {
                        return [false, $row, [], $dent, [7, $t]];
                    }
                    // <https://spec.commonmark.org/0.31.2#open-tag>
                    if (\preg_match('/^<[a-z][a-z\d-]*(\s+[a-z:_][\w.:-]*(\s*=\s*(?>"[^"]*"|\'[^\']*\'|[^\s"\'<=>`]+)?)?)*\s*\/?>$/i', $test)) {
                        return [false, $row, [], $dent, [7, $t]];
                    }
                    return ['p', $row, [], $dent];
                }
            }
            return ['p', $row, [], $dent];
        }
        // `=…`
        if (0 === \strpos($row, '=')) {
            if (\strspn($row, '=') === \strlen($row)) {
                return ['h1', $row, [], $dent, ['=', 1]]; // Look like a Setext-header level 1
            }
            return ['p', $row, [], $dent];
        }
        // `>…`
        if (0 === \strpos($row, '>')) {
            return ['blockquote', $row, [], $dent];
        }
        // `[…`
        if (0 === \strpos($row, '[')) {
            if (1 === \strpos($row, '^')) {
                return [2, $row, [], $dent];
            }
            if (
                // `[asdf](…`
                \strpos($row, '](') > 0 ||
                // `[asdf][…`
                \strpos($row, '][') > 0 ||
                // `[asdf]` not followed by a `:`
                false !== ($n = \strpos($row, ']')) && ':' !== \substr($row, $n + 1, 1)
            ) {
                return ['p', $row, [], $dent];
            }
            return [0, $row, [], $dent];
        }
        // `_…`
        if (0 === \strpos($row, '_')) {
            // `___`
            $test = \strtr($row, [' ' => ""]);
            if (\strspn($test, '_') === ($n = \strlen($test)) && $n > 2) {
                return ['hr', $row, [], $dent, ['_', $n]];
            }
            return ['p', $row, [], $dent];
        }
        // ``…`
        if (0 === \strpos($row, '`') && ($n = \strspn($row, '`')) >= 3) {
            $info = \trim(\substr($row, $n));
            // <https://spec.commonmark.org/0.30#example-145>
            if (false !== \strpos($info, '`')) {
                return ['p', $row, [], $dent];
            }
            return ['pre', $row, a($info, true), $dent, [2, $n]];
        }
        // `~…`
        if (0 === \strpos($row, '~') && ($n = \strspn($row, '~')) >= 3) {
            $info = \trim(\substr($row, $n));
            return ['pre', $row, a($info, true), $dent, [3, $n]];
        }
        // `1…`
        $n = \strspn($row, '0123456789');
        // <https://spec.commonmark.org/0.30#example-266>
        if ($n > 9) {
            return ['p', $row, [], $dent];
        }
        // `1)` or `1.`
        if ($n && ($n + 1) === \strlen($v = \rtrim($row)) && false !== \strpos(').', \substr($v, -1))) {
            $start = (int) \substr($row, 0, $n);
            return ['ol', $row, ['start' => 1 !== $start ? $start : null], $dent, [\substr($row, -1), $n + 2, $start]];
        }
        // `1) …` or `1. …`
        if (false !== \strpos(').', \substr($row, $n, 1)) && ' ' === \substr($row, $n + 1, 1)) {
            $r = \strspn($row, ' ', $n + 1);
            $start = (int) \substr($row, 0, $n);
            return ['ol', $row, ['start' => 1 !== $start ? $start : null], $dent, [\substr($row, $n, 1), $n + 1 + ($r > 3 ? 3 : $r), $start]];
        }
        return ['p', $row, [], $dent];
    }
    function q(string $char = '"', $key = false, string $before = "", string $x = ""): string {
        $a = \preg_quote($char[0], '/');
        $b = \preg_quote($char[1] ?? $char[0], '/');
        $c = $a . ($b === $a ? "" : $b);
        return '(?>' . $a . ($key ? '(' . (\is_string($key) ? '?<' . $key . '>' : "") : "") . '(?>' . ($before ? $before . '|' : "") . '[^' . $c . $x . '\\\\]|\\\\.)*+' . ($key ? ')' : "") . $b . ')';
    }
    function r(string $char = '[]', $key = false, string $before = "", string $x = ""): string {
        $a = \preg_quote($char[0], '/');
        $b = \preg_quote($char[1] ?? $char[0], '/');
        $c = $a . ($b === $a ? "" : $b);
        return '(?>' . $a . ($key ? '(' . (\is_string($key) ? '?<' . $key . '>' : "") : "") . '(?>' . ($before ? $before . '|' : "") . '[^' . $c . $x . '\\\\]|\\\\.|(?R))*+' . ($key ? ')' : "") . $b . ')';
    }
    function raw(?string $value, $block = true): array {
        return $block ? rows($value) : row($value);
    }
    function row(?string $value, array &$lot = []) {
        if ("" === \trim($value ?? "")) {
            return [[], $lot];
        }
        $chops = [];
        $is_img = isset($lot['is']['img']);
        $is_table = isset($lot['is']['table']);
        $notes = $lot['notes'] ?? [];
        while (false !== ($chop = \strpbrk($value, "\\" . '<`' . ($is_table ? '|' : "") . '*_![&' . "\n"))) {
            if ("" !== ($v = \strstr($value, $c = $chop[0], true))) {
                if (\is_array($abbr = abbr($v, $lot))) {
                    $chops = \array_merge($chops, $abbr);
                } else {
                    $chops[] = e($v);
                }
                $value = $chop;
            }
            if ("\\" === $c) {
                if ("\\" === \trim($chop)) {
                    $chops[] = "\\";
                    $value = "";
                    // A back-slash is not a hard break when it is at the end of a paragraph block
                    break;
                }
                // <https://spec.commonmark.org/0.31.2#example-644>
                if ("\n" === ($chop[1] ?? 0)) {
                    $chops[] = ['br', false, [], -1, ["\\", 1]];
                    $value = \ltrim(\substr($chop, 2));
                    continue;
                }
                // Un-escape a character
                $chops[] = e(\substr($chop, 1, 1));
                $value = \substr($chop, 2);
                continue;
            }
            if ("\n" === $c) {
                $v = $chops[$n = \count($chops) - 1] ?? [];
                if (\is_string($v) && '  ' === \substr($v, -2)) {
                    $chops[$n] = \rtrim($v);
                    $chops[] = ['br', false, [], -1, [' ', 2]];
                    $value = \ltrim(\substr($chop, 1));
                    continue;
                }
                // Collapse current line to the previous line
                $chops[] = ' ';
                $value = \substr($chop, 1);
                continue;
            }
            if ('!' === $c) {
                if ('[' !== ($chop[1] ?? 0)) {
                    $chops[] = $c;
                    $value = \substr($chop, 1);
                    continue;
                }
                $lot['is']['img'] = 1;
                $test = row(\substr($chop, 1), $lot)[0][0];
                unset($lot['is']['img']);
                if (\is_array($test) && 'a' === $test[0]) {
                    $test[0] = 'img';
                    if (\is_array($test[1])) {
                        $alt = "";
                        foreach ($test[1] as $v) {
                            // <https://spec.commonmark.org/0.30#example-573>
                            if (\is_array($v) && 'img' === $v[0]) {
                                $alt .= $v[2]['alt'] ?? "";
                                continue;
                            }
                            $alt .= \is_array($v) ? s($v) : $v;
                        }
                    } else {
                        $alt = $test[1];
                    }
                    $test[1] = false;
                    // <https://spec.commonmark.org/0.30#example-572>
                    $test[2]['alt'] = \trim(\strip_tags($alt));
                    $test[2]['src'] = $test[2]['href'];
                    $test[4][1] = '!' . $test[4][1];
                    unset($test[2]['href'], $test[2]['rel'], $test[2]['target']);
                    $chops[] = $test;
                    $value = \substr($chop, \strlen($test[4][1]));
                    continue;
                }
                $chops[] = $c;
                $value = \substr($chop, 1);
                continue;
            }
            if ('&' === $c) {
                if (false === ($n = \strpos($chop, ';')) || $n < 2 || !\preg_match('/^&(?>#x[a-f\d]{1,6}|#\d{1,7}|[a-z][a-z\d]{1,31});/i', $chop, $m)) {
                    $chops[] = e('&');
                    $value = \substr($chop, 1);
                    continue;
                }
                // <https://spec.commonmark.org/0.30#example-26>
                if ('&#0;' === $m[0]) {
                    $m[0] = '&#xfffd;';
                }
                $chops[] = ['&', $m[0], [], -1];
                $value = \substr($chop, \strlen($m[0]));
                continue;
            }
            if ('*' === $c) {
                $contains = '`[^`]+`|\\\\.|[^*' . ($is_table ? '|' : "") . ']';
                if (
                    // Prefer `<em><strong>…</strong></em>`
                    \preg_match('/^((\*)((\*\*)(?!\s)(?>' . $contains . ')++(?<!\s)\4)\2)/', $chop, $m) ||
                    \preg_match('/^((\*\*)(?!\s)((?>' . $contains . '|(\*)(?!\s)(?>' . $contains . ')+?(?<!\s)\4|\B\2(?!\s)(?>' . $contains . ')+?(?<!\s)\2\B)++)(?<!\s)\2)/', $chop, $m) ||
                    \preg_match('/^((\*)(?!\s)((?>' . $contains . '|(\*\*)(?!\s)(?>' . $contains . ')+?(?<!\s)\4|\B\2(?!\s)(?>' . $contains . ')+?(?<!\s)\2\B)++)(?<!\s)\2)/', $chop, $m)
                ) {
                    // <https://spec.commonmark.org/0.31.2#example-521>
                    if (false !== ($n = \strpos($m[0], '[')) && (false === \strpos($m[0], ']') || !\preg_match('/' . r('[]') . '/', $m[0]))) {
                        $chops[] = e(\substr($chop, 0, $n));
                        $value = \substr($chop, $n);
                        continue;
                    }
                    $chops[] = [1 === ($n = \strlen($m[2])) ? 'em' : 'strong', row($m[3], $lot)[0], [], -1, [$c, $n]];
                    $value = \substr($chop, \strlen($m[0]));
                    continue;
                }
                $chops[] = e(\substr($chop, 0, $n = \strspn($chop, $c)));
                $value = \substr($chop, $n);
                continue;
            }
            if ('<' === $c) {
                // <https://spec.commonmark.org/0.31.2#processing-instruction>
                if (0 === \strpos($chop, '<' . '?') && ($n = \strpos($chop, '?' . '>')) > 1) {
                    $v = \strtr(\substr($chop, 0, $n += 2), "\n", ' ');
                    $chops[] = [false, $v, [], -1, [3, '?' . \trim(\strtok(\substr($v, 1), ' >'), '?')]];
                    $value = \substr($chop, $n);
                    continue;
                }
                // <https://spec.commonmark.org/0.31.2#html-comment>
                if (0 === \strpos($chop, '<!--') && ($n = \strpos($chop, '-->')) > 1) {
                    $chops[] = [false, \strtr(\substr($chop, 0, $n += 3), "\n", ' '), [], -1, [2, '!--']];
                    $value = \substr($chop, $n);
                    continue;
                }
                // <https://spec.commonmark.org/0.31.2#cdata-section>
                if (0 === \strpos($chop, '<![CDATA[') && ($n = \strpos($chop, ']]>')) > 8) {
                    $chops[] = [false, \strtr(\substr($chop, 0, $n += 3), "\n", ' '), [], -1, [5, '![CDATA[']];
                    $value = \substr($chop, $n);
                    continue;
                }
                $test = (string) \strstr($chop, '>', true);
                // <https://github.com/commonmark/commonmark.js/blob/df3ea1e80d98fce5ad7c72505f9230faa6f23492/lib/inlines.js#L73>
                if (\strpos($test, '@') > 0 && \preg_match('/^<([a-z\d!#$%&\'*+.\/=?^_`{|}~-]+@[a-z\d](?>[a-z\d-]{0,61}[a-z\d])?(?>\.[a-z\d](?>[a-z\d-]{0,61}[a-z\d])?)*)>/i', $chop, $m)) {
                    // <https://spec.commonmark.org/0.30#example-605>
                    if (false !== \strpos($email = $m[1], '\\')) {
                        $chops[] = e($m[0]);
                        $value = \substr($chop, \strlen($m[0]));
                        continue;
                    }
                    $chops[] = ['a', e($m[1]), ['href' => u('mailto:' . $email)], -1, [false, $m[0]]];
                    $value = \substr($chop, \strlen($m[0]));
                    continue;
                }
                // <https://github.com/commonmark/commonmark.js/blob/df3ea1e80d98fce5ad7c72505f9230faa6f23492/lib/inlines.js#L75>
                if (\strpos($test, ':') > 1 && \preg_match('/^<([a-z][a-z\d.+-]{1,31}:(?>' . ($is_table ? '\\\\\||[^|' : '[^') . '<>\x00-\x20])*)>/i', $chop, $m)) {
                    $rel = $target = null;
                    if (!l($link = $is_table ? \strtr($m[1], ["\\|" => '|']) : $m[1])) {
                        $rel = 'nofollow';
                        $target = '_blank';
                    }
                    $chops[] = ['a', e($link), [
                        'href' => u($link),
                        'rel' => $rel,
                        'target' => $target
                    ], -1, [false, $m[0]]];
                    $value = \substr($chop, \strlen($m[0]));
                    continue;
                }
                // <https://spec.commonmark.org/0.31.2#closing-tag>
                if ('/' === $chop[1] && \preg_match('/^<(\/[a-z][a-z\d-]*)\s*>/i', $chop, $m)) {
                    $chops[] = [false, $m[0], [], -1, [7, $m[1]]];
                    $value = \substr($chop, \strlen($m[0]));
                    continue;
                }
                // <https://spec.commonmark.org/0.31.2#open-tag>
                if (\preg_match('/^<([a-z][a-z\d-]*)(\s+[a-z:_][\w.:-]*(\s*=\s*(?>"[^"]*"|\'[^\']*\'|[^' . ($is_table ? '|' : "") . '\s"\'<=>`]+)?)?)*\s*\/?>/i', $chop, $m)) {
                    $chops[] = [false, $m[0], [], -1, [7, $m[1]]];
                    $value = \substr($chop, \strlen($m[0]));
                    continue;
                }
                $chops[] = e('<');
                $value = \substr($chop, 1);
                continue;
            }
            if ('[' === $c) {
                $contains = '`[^`]+`';
                $data = $key = $link = $title = null;
                // `[asdf]…`
                if (\preg_match('/' . r('[]', true, $contains, $is_table ? '|' : "") . '/', $chop, $m, \PREG_OFFSET_CAPTURE)) {
                    if ($m[0][1] > 0) {
                        $chops[] = e(\substr($chop, 0, $m[0][1]));
                        $value = $chop = \substr($chop, $m[0][1]);
                    }
                    // <https://spec.commonmark.org/0.31.2#example-518>
                    if (!$is_img && \preg_match('/(?<!!)' . q('[]', true, $contains, $is_table ? '|' : "") . '/', $v = $m[0][0], $n, \PREG_OFFSET_CAPTURE) && $n[0][1] > 0) {
                        if (false !== \strpos($v, '](') || false !== \strpos($v, '][') || isset($lot[0][\trim(\strtolower($n[1][0]))])) {
                            $chops[] = e($v = \substr($v, 0, $n[0][1]));
                            $value = \substr($chop, \strlen($v));
                            continue;
                        }
                    }
                    $value = $chop = \substr($chop, \strlen($m[0][0]));
                    // `[^asdf]`
                    if (0 === \strpos($m[1][0], '^') && ("" === $chop || false === \strpos('([', $chop[0]))) {
                        if (!isset($lot[2][$key = \trim(\substr($m[1][0], 1))])) {
                            $chops[] = e($m[0][0]);
                            continue;
                        }
                        $notes[$key] = ($notes[$key] ?? 0) + 1;
                        $chops[] = ['sup', [['a', (string) \count($notes), [
                            'href' => '#to:' . $key,
                            'role' => 'doc-noteref'
                        ], -1, [false, $m[0][0]]]], [
                            'id' => 'from:' . $key . ($notes[$key] > 1 ? '.' . $notes[$key] : "")
                        ], -1, [$key, $m[0][0]]];
                        $lot['notes'] = $notes;
                        continue;
                    }
                    $row = row($m[1][0], $lot)[0];
                    // `…(asdf)`
                    if (0 === \strpos($chop, '(') && \preg_match('/' . r('()', true, q('<>'), $is_table ? '|' : "") . '/', $chop, $n, \PREG_OFFSET_CAPTURE)) {
                        if ($n[0][1] > 0) {
                            $chops[] = e($m[0][0] . \substr($chop, 0, $n[0][1]));
                            $value = \substr($chop, $n[0][1]);
                            continue;
                        }
                        $test = \trim($n[1][0]);
                        // `[asdf]()` or `[asdf](<>)`
                        if ("" === $test || '<>' === $test) {
                            $link = "";
                        // `[asdf](<asdf>)`
                        } else if ('<' === $test[0]) {
                            if (false !== ($v = \strstr(\substr($test, 1), '>', true))) {
                                $link = $v;
                                $title = \trim(\substr($test, \strlen($v) + 2));
                                $title = "" !== $title ? $title : null;
                            } else {
                                $link = $test;
                            }
                        // `[asdf](asdf …)`
                        } else if (\strpos($test, ' ') > 0 || \strpos($test, "\n") > 0) {
                            $link = \trim(\strtok($test, " \n"));
                            $title = \trim(\strpbrk($test, " \n"));
                        // `[asdf](asdf)`
                        } else {
                            $link = $test;
                        }
                        // <https://spec.commonmark.org/0.31.2#example-490>
                        // <https://spec.commonmark.org/0.31.2#example-493>
                        // <https://spec.commonmark.org/0.31.2#example-494>
                        if (false !== \strpos($link, "\n") || '\\' === \substr($link, -1) || 0 === \strpos($link, '<')) {
                            $chops[] = e(v(\strtr($m[0][0] . $n[0][0], "\n", ' ')));
                            $value = \substr($chop, \strlen($n[0][0]));
                            continue;
                        }
                        if (\is_string($title) && "" !== $title) {
                            // `[asdf](asdf "asdf")` or `[asdf](asdf 'asdf')` or `[asdf](asdf (asdf))`
                            $a = $title[0];
                            $b = \substr($title, -1);
                            if (('"' === $a && '"' === $b || "'" === $a && "'" === $b || '(' === $a && ')' === $b) && \preg_match('/^' . q($a . $b) . '$/', $title)) {
                                $title = v(d(\substr($title, 1, -1)));
                            // `[asdf](asdf asdf)`
                            // <https://spec.commonmark.org/0.31.2#example-488>
                            } else {
                                $chops[] = e(v(\strtr($m[0][0] . $n[0][0], "\n", ' ')));
                                $value = \substr($chop, \strlen($n[0][0]));
                                continue;
                            }
                        }
                        $key = false;
                        $value = $chop = \substr($chop, \strlen($n[0][0]));
                    // `…[]` or `…[asdf]`
                    } else if (0 === \strpos($chop, '[') && \preg_match('/' . r('[]', true, $contains, $is_table ? '|' : "") . '/', $chop, $n, \PREG_OFFSET_CAPTURE)) {
                        $value = $chop = \substr($chop, \strlen($n[0][0]));
                        if (!isset($lot[0][$key = \trim(\strtolower("" === $n[1][0] ? $m[1][0] : $n[1][0]))])) {
                            $chops[] = e($m[0][0] . $n[0][0]);
                            continue;
                        }
                    }
                    $key = $key ?? \trim(\strtolower($m[1][0]));
                    if (\is_string($key) && !isset($lot[0][$key])) {
                        $chops[] = e($m[0][0]);
                        continue;
                    }
                    // …{asdf}
                    if (0 === \strpos(\trim($chop), '{') && \preg_match('/^\s*(' . q('{}', false, q('"') . '|' . q("'"), $is_table ? '|' : "") . ')/', $chop, $o)) {
                        if ("" !== \trim(\substr($o[1], 1, -1))) {
                            $data = \array_replace($data ?? [], a($o[1], true));
                            $value = \substr($chop, \strlen($o[0]));
                        }
                    }
                    if (false !== $key) {
                        $data = \array_replace($lot[0][$key][2] ?? [], $data ?? []);
                        $link = $lot[0][$key][0] ?? null;
                        $title = $lot[0][$key][1] ?? null;
                    }
                    if (!l($link)) {
                        $data['rel'] = $data['rel'] ?? 'nofollow';
                        $data['target'] = $data['target'] ?? '_blank';
                    }
                    $chops[] = ['a', $row, \array_replace([
                        'href' => u(v($link)),
                        'title' => $title
                    ], $data ?? []), -1, [$key, $m[0][0] . ($n[0][0] ?? "") . ($o[0] ?? "")]];
                    continue;
                }
                $chops[] = '[';
                $value = \substr($chop, 1);
                continue;
            }
            if ('_' === $c) {
                $contains = '`[^`]+`|\\\\.|[' . ($is_table ? '^|' : '\s\S') . ']';
                $last = $chops[\count($chops) - 1] ?? 0;
                if ($last && \is_string($last) && \preg_match('/\b/', \substr($last, -1))) {
                    $chops[] = e(\substr($chop, 0, $n = \strspn($chop, $c)));
                    $value = \substr($chop, $n);
                    continue;
                }
                if (
                    // Prefer `<em><strong>…</strong></em>`
                    \preg_match('/^(_)((__)(?![_\s])(?>' . $contains . ')+?(?<![_\s])\3)\1/', $chop, $m) ||
                    \preg_match('/^(__)(?!\s)((?>' . $contains . ')+?)(?<!\s)\1\b/', $chop, $m) ||
                    \preg_match('/^(_)(?!\s)((?>' . $contains . ')+?)(?<!\s)\1\b/', $chop, $m)
                ) {
                    // <https://spec.commonmark.org/0.31.2#example-521>
                    if (false !== ($n = \strpos($m[0], '[')) && (false === \strpos($m[0], ']') || !\preg_match('/' . r('[]') . '/', $m[0]))) {
                        $chops[] = e(\substr($chop, 0, $n));
                        $value = \substr($chop, $n);
                        continue;
                    }
                    $chops[] = [1 === ($n = \strlen($m[1])) ? 'em' : 'strong', row($m[2], $lot)[0], [], -1, [$c, $n]];
                    $value = \substr($chop, \strlen($m[0]));
                    continue;
                }
                $chops[] = e(\substr($chop, 0, $n = \strspn($chop, $c)));
                $value = \substr($chop, $n);
                continue;
            }
            if ('`' === $c) {
                if (\preg_match('/^(`+)(?!`)(.+?)(?<!`)\1(?!`)/s', $chop, $m)) {
                    // <https://spec.commonmark.org/0.31.2#code-spans>
                    $raw = \strtr($m[2], "\n", ' ');
                    if ("" !== \trim($raw) && ' ' === $raw[0] && ' ' === \substr($raw, -1)) {
                        $raw = \substr($raw, 1, -1);
                    }
                    $chops[] = ['code', e($raw), [], -1, [$c, \strlen($m[1])]];
                    $value = \substr($chop, \strlen($m[0]));
                    continue;
                }
                $chops[] = \str_repeat($c, $n = \strspn($chop, $c));
                $value = \substr($chop, $n);
                continue;
            }
            if ($is_table && '|' === $c) {
                $chops[] = [false, $c, [], -1];
                $value = \substr($chop, 1);
                continue;
            }
            if (\is_array($abbr = abbr($v = $chop, $lot))) {
                $chops = \array_merge($chops, $abbr);
            } else {
                $chops[] = e($v);
            }
            $value = "";
        }
        if ("" !== $value) {
            if (\is_array($abbr = abbr($v = $value, $lot))) {
                $chops = \array_merge($chops, $abbr);
            } else {
                $chops[] = e($v);
            }
        }
        return [m($chops), $lot];
    }
    function rows(?string $value, array &$lot = [], int $level = 0): array {
        // List of reference(s), abbreviation(s), and note(s)
        $lot = \array_replace([[], [], []], $lot);
        if ("" === \trim($value ?? "")) {
            return [[], $lot];
        }
        // Normalize line break(s)
        $value = \trim(\strtr($value, [
            "\r\n" => "\n",
            "\r" => "\n"
        ]), "\n");
        $block = -1;
        $blocks = [];
        $rows = \explode("\n", $value);
        foreach ($rows as $row) {
            // Normalize tab(s) to pad(s)
            $row = \rtrim($row, "\t");
            while (false !== ($pad = \strstr($row, "\t", true))) {
                $row = $pad . \str_repeat(' ', 4 - ($v = \strlen($pad)) % 4) . \substr($row, $v + 1);
            }
            $of = of($row);
            if ($last = $blocks[$block] ?? 0) {
                // Last block is a code block
                if ('pre' === $last[0]) {
                    if (2 === $last[4][0] || 3 === $last[4][0]) {
                        // End of the code block
                        if ('pre' === $of[0] && $last[4][0] === $of[4][0] && $last[4][1] === $of[4][1]) {
                            $fence = \rtrim(\strstr($of[1], "\n", true) ?: $of[1]);
                            // End of the code block cannot have an info string
                            if (\strlen($fence) !== $of[4][1]) {
                                $blocks[$block][1] .= "\n" . $of[1];
                                continue;
                            }
                            $blocks[$block++][1] .= "\n" . $of[1];
                            continue;
                        }
                        // Continue the code block…
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    // End of the code block
                    if (null !== $of[0] && 'pre' !== $of[0]) {
                        if ("\n" === \substr($v = \rtrim($last[1], ' '), -1)) {
                            $blocks[$block][1] = \substr($v, 0, -1);
                        }
                        $blocks[++$block] = $of;
                        continue;
                    }
                    // Continue the code block…
                    $blocks[$block][1] .= "\n" . $of[1];
                    continue;
                }
                // Last block is a raw block
                if (false === $last[0]) {
                    if (1 === $last[4][0]) {
                        if (false !== \strpos($last[1], '</' . $last[4][1] . '>')) {
                            if (null === $of[0]) {
                                $block += 1;
                                continue;
                            }
                            $blocks[++$block] = $of;
                            continue;
                        }
                        if (null !== $of[0] && false !== \strpos($of[1], '</' . $last[4][1] . '>')) {
                            $blocks[$block++][1] .= "\n" . $row;
                            continue;
                        }
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    if (2 === $last[4][0]) {
                        if (false !== \strpos($last[1], '-->')) {
                            if (null === $of[0]) {
                                $block += 1;
                                continue;
                            }
                            $blocks[++$block] = $of;
                            continue;
                        }
                        if (null !== $of[0] && false !== \strpos($of[1], '-->')) {
                            $blocks[$block++][1] .= "\n" . $row;
                            continue;
                        }
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    if (3 === $last[4][0]) {
                        if (false !== \strpos($last[1], '?' . '>')) {
                            if (null === $of[0]) {
                                $block += 1;
                                continue;
                            }
                            $blocks[++$block] = $of;
                            continue;
                        }
                        if (null !== $of[0] && false !== \strpos($of[1], '?' . '>')) {
                            $blocks[$block++][1] .= "\n" . $row;
                            continue;
                        }
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    if (4 === $last[4][0]) {
                        if (false !== \strpos($last[1], '>')) {
                            if (null === $of[0]) {
                                $block += 1;
                                continue;
                            }
                            $blocks[++$block] = $of;
                            continue;
                        }
                        if (null !== $of[0] && false !== \strpos($of[1], '>')) {
                            $blocks[$block++][1] .= "\n" . $row;
                            continue;
                        }
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    if (5 === $last[4][0]) {
                        if (false !== \strpos($last[1], ']]>')) {
                            if (null === $of[0]) {
                                $block += 1;
                                continue;
                            }
                            $blocks[++$block] = $of;
                            continue;
                        }
                        if (null !== $of[0] && false !== \strpos($of[1], ']]>')) {
                            $blocks[$block++][1] .= "\n" . $row;
                            continue;
                        }
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    if (null === $of[0]) {
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][1] .= "\n" . $row;
                    continue;
                }
                // Last block is an abbreviation, note, or reference
                if (\is_int($last[0])) {
                    if (2 === $last[0]) {
                        // Must have at least 1 white-space after the `]:`
                        if (false !== ($n = \strpos($last[1], ']:')) && "\\" !== \substr($last[1], $n - 1, 1) && false === \strpos(" \n", \substr($last[1], $n + 2, 1))) {
                            $blocks[$block][0] = 'p';
                            if (null === $of[0]) {
                                $block += 1;
                                continue;
                            }
                            $blocks[$block][1] .= "\n" . $of[1];
                            continue;
                        }
                        // End of the note block
                        if (null !== $of[0] && $of[3] <= $last[3]) {
                            if ("\n" === \substr($v = \rtrim($last[1], ' '), -1)) {
                                $blocks[$block][1] = $v = \substr($v, 0, -1);
                            } else {
                                // Lazy note block?
                                if ('p' === $of[0]) {
                                    $blocks[$block][1] .= "\n" . $of[1];
                                    continue;
                                }
                            }
                            // Note block must not be empty
                            if (']:' === \substr($v, -2) && "\\" !== \substr($v, -3, 1)) {
                                $blocks[$block++][0] = 'p';
                            }
                            $blocks[++$block] = $of;
                            continue;
                        }
                        // Continue the note block…
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    if (\is_int($of[0])) {
                        $blocks[++$block] = $of;
                        continue;
                    }
                    if (null === $of[0]) {
                        if (false === ($n = \strpos($last[1], ']:')) || "\\" === \substr($last[1], $n - 1, 1)) {
                            $blocks[$block][0] = 'p';
                        }
                        $block += 1;
                        continue;
                    }
                    $blocks[$block][1] .= "\n" . $row;
                    continue;
                }
                // Last block is a figure block
                if ('figure' === $last[0]) {
                    // End of the figure block
                    if (null !== $of[0] && $of[3] <= $last[3]) {
                        $blocks[++$block] = $of;
                        continue;
                    }
                    if ($of[3] > $last[3] || "" === $of[1]) {
                        $row = \substr($row, 1);
                        $blocks[$block][4] = isset($last[4]) ? $last[4] . "\n" . $row : $row;
                        continue;
                    }
                    // Continue the figure block…
                    $blocks[$block][1] .= "\n" . $of[1];
                    continue;
                }
                // Last block is a table block
                if ('table' === $last[0]) {
                    if (null === $of[0] || $of[3] > $last[3]) {
                        $blocks[$block][1] .= "\n"; // End of the table block, prepare to exit the table block
                        $blocks[$block][4] = isset($last[4]) ? $last[4] . "\n" . $row : $row;
                        continue;
                    }
                    // Continue the table block if last table block does not end with a blank line
                    if ('table' === $of[0] && "\n" !== \substr(\rtrim($last[1], ' '), -1)) {
                        $blocks[$block][1] .= "\n" . $of[1];
                        continue;
                    }
                    // End of the table block
                    $blocks[++$block] = $of;
                    continue;
                }
                // Last block is a header block or a rule block
                if ('h' === $last[0][0]) {
                    if (null === $of[0]) {
                        $block += 1;
                        continue;
                    }
                    $blocks[++$block] = $of;
                    continue;
                }
                // Last block is a definition list block
                if ('dl' === $last[0]) {
                    // Continue the list block…
                    if (null === $of[0] || $of[3] >= $last[3] + $last[4][1]) {
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    // <https://spec.commonmark.org/0.31.2#example-312>
                    if ($of[3] > 3) {
                        $blocks[$block][1] .= ' ' . \ltrim($of[1]);
                        continue;
                    }
                    if ('dl' === $of[0]) {
                        $blocks[$block][1] .= "\n" . \substr($row, $of[3]);
                        $blocks[$block][3] = $of[3];
                        continue;
                    }
                    // Lazy definition list?
                    if ("\n" !== \substr($v = \rtrim($last[1], ' '), -1)) {
                        if ('h1' === $of[0] && '=' === $of[4][0]) {
                            $blocks[$block][1] .= ' ' . $of[1];
                            continue;
                        }
                        if ('p' === $of[0] && $v !== $last[4][0]) {
                            $blocks[$block][1] .= "\n" . $row;
                            continue;
                        }
                    } else {
                        $blocks[$block][1] = \substr($v, 0, -1);
                    }
                    // End of the definition list block
                    $blocks[++$block] = $of;
                    continue;
                }
                // Last block is a list block
                if ('ol' === $last[0]) {
                    // Continue the list block…
                    if (null === $of[0] || $of[3] >= $last[3] + $last[4][1]) {
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    // <https://spec.commonmark.org/0.31.2#example-312>
                    if ($of[3] > 3) {
                        $blocks[$block][1] .= ' ' . \ltrim($of[1]);
                        continue;
                    }
                    if ('ol' === $of[0]) {
                        // End of the list block
                        if ($of[4][0] !== $last[4][0]) {
                            $blocks[++$block] = $of;
                            continue;
                        }
                        // Continue the list block…
                        if ($of[4][2] >= $last[4][2]) {
                            $blocks[$block][1] .= "\n" . \substr($row, $of[3]);
                            $blocks[$block][3] = $of[3];
                            $blocks[$block][4][2] = $of[4][2];
                            continue;
                        }
                        // End of the list block
                        $blocks[++$block] = $of;
                        continue;
                    }
                    // Lazy list?
                    if ("\n" !== \substr($v = \rtrim($last[1], ' '), -1)) {
                        if ('h1' === $of[0] && '=' === $of[4][0]) {
                            $blocks[$block][1] .= ' ' . $of[1];
                            continue;
                        }
                        if ('p' === $of[0] && $v !== $last[4][2] . $last[4][0]) {
                            // Hot fix for case `1) 1)\nasdf`
                            if (\preg_match('/(^|\n)\d+[).]([ ]+\d+[).])+[ ]*$/', $last[1])) {
                                $blocks[++$block] = $of;
                                continue;
                            }
                            $blocks[$block][1] .= "\n" . $row;
                            continue;
                        }
                    } else {
                        $blocks[$block][1] = \substr($v, 0, -1);
                    }
                    // End of the list block
                    $blocks[++$block] = $of;
                    continue;
                }
                // Last block is a list block
                if ('ul' === $last[0]) {
                    if (null === $of[0] || $of[3] >= $last[3] + $last[4][1]) {
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    // <https://spec.commonmark.org/0.31.2#example-312>
                    if ($of[3] > 3) {
                        $blocks[$block][1] .= ' ' . \ltrim($of[1]);
                        continue;
                    }
                    if ('ul' === $of[0]) {
                        // End of the list block
                        if ($of[4][0] !== $last[4][0]) {
                            $blocks[++$block] = $of;
                            continue;
                        }
                        $blocks[$block][1] .= "\n" . \substr($row, $of[3]);
                        $blocks[$block][3] = $of[3];
                        continue;
                    }
                    // Lazy list?
                    if ("\n" !== \substr($v = \rtrim($last[1], ' '), -1)) {
                        if ('h1' === $of[0] && '=' === $of[4][0]) {
                            $blocks[$block][1] .= ' ' . $of[1];
                            continue;
                        }
                        if ('p' === $of[0] && $v !== $last[4][0]) {
                            // Hot fix for case `* *\nasdf`
                            if (\preg_match('/(^|\n)[*+-]([ ]+[*+-])+[ ]*$/', $last[1])) {
                                $blocks[++$block] = $of;
                                continue;
                            }
                            $blocks[$block][1] .= "\n" . $row;
                            continue;
                        }
                    } else {
                        $blocks[$block][1] = \substr($v, 0, -1);
                    }
                    // End of the list block
                    $blocks[++$block] = $of;
                    continue;
                }
                // Start a new tight raw block…
                if (false === $of[0] && 7 !== $of[4][0]) {
                    $blocks[++$block] = $of;
                    continue;
                }
                // Start a new tight block…
                if (\is_string($last[0]) && \is_string($of[0]) && $last[0] !== $of[0]) {
                    // Lazy quote?
                    if ('blockquote' === $last[0] && '>' !== \rtrim($last[1]) && 'p' === $of[0]) {
                        $blocks[$block][1] .= ' ' . $of[1];
                        continue;
                    }
                    if ('hr' === $of[0] && '-' === $of[4][0] && \strlen($of[1]) === \strspn($of[1], '-')) {
                        if ('p' !== $last[0]) {
                            $blocks[++$block] = $of;
                            continue;
                        }
                        $blocks[$block][0] = 'h2';
                        $blocks[$block][1] .= "\n" . $of[1];
                        $blocks[$block][4] = ['-', 2];
                        continue;
                    }
                    if ('h1' === $of[0] && '=' === $of[4][0]) {
                        if ('p' !== $last[0]) {
                            // <https://spec.commonmark.org/0.31.2#example-93>
                            $blocks[$block][1] .= ' ' . $of[1];
                            continue;
                        }
                        $blocks[$block][0] = 'h1';
                        $blocks[$block][1] .= "\n" . $of[1];
                        $blocks[$block][4] = ['=', 1];
                        continue;
                    }
                    if ('h2' === $of[0] && '-' === $of[4][0]) {
                        if ('p' !== $last[0]) {
                            $blocks[$block][1] .= ' ' . $of[1];
                            continue;
                        }
                        $blocks[$block][0] = 'h2';
                        $blocks[$block][1] .= "\n" . $of[1];
                        $blocks[$block][4] = ['-', 2];
                        continue;
                    }
                    if ('pre' === $of[0] && 1 === $of[4][0] && 'p' === $last[0]) {
                        // Contains a hard-break syntax, keep!
                        if ('  ' === \substr($last[1], -2)) {
                            $blocks[$block][1] = \rtrim($last[1]) . "  \n" . $of[1];
                            continue;
                        }
                        // Contains a hard-break syntax, keep!
                        if ("\\" === \substr($v = \rtrim($last[1]), -1)) {
                            $blocks[$block][1] = $v . "\n" . $of[1];
                            continue;
                        }
                        $blocks[$block][1] .= ' ' . $of[1];
                        continue;
                    }
                    if ('dl' === $of[0] && 'p' === $last[0]) {
                        $blocks[$block][0] = 'dl';
                        $blocks[$block][1] .= "\n" . $row;
                        $blocks[$block][4] = $of[4];
                        continue;
                    }
                    if ('ol' === $of[0]) {
                        if (\rtrim($of[1]) === $of[4][2] . $of[4][0]) {
                            $blocks[$block][1] .= "\n" . $row;
                            continue;
                        }
                        // <https://spec.commonmark.org/0.31.2#example-304>
                        if (1 !== $of[4][2]) {
                            $blocks[$block][1] .= "\n" . $row;
                            continue;
                        }
                    }
                    if ('ul' === $of[0] && \rtrim($of[1]) === $of[4][0]) {
                        if ('p' === $last[0] && '-' === $of[4][0]) {
                            $blocks[$block][0] = 'h2';
                            $blocks[$block][1] .= "\n-";
                            $blocks[$block][4] = ['-', 2];
                            continue;
                        }
                        $blocks[$block][1] .= "\n" . $row;
                        continue;
                    }
                    $blocks[++$block] = $of;
                    continue;
                }
                // Current block is empty, start a new block…
                if (null === $of[0]) {
                    $block += 1;
                    continue;
                }
                // Continue the last block…
                $blocks[$block][1] .= "\n" . $of[1];
                continue;
            }
            // Current block is empty, skip!
            if (null === $of[0]) {
                continue;
            }
            // Start a new block…
            $blocks[++$block] = $of;
        }
        foreach ($blocks as $k => &$v) {
            if (!\is_int($v[0])) {
                continue;
            }
            // `*[asdf]:`
            if (0 === \strpos($v[1], '*[') && \preg_match('/' . r('[]', true) . '/', \substr($v[1], 1), $m) && ':' === \substr($v[1], \strlen($m[0]) + 1, 1)) {
                // Remove abbreviation block from the structure
                unset($blocks[$k]);
                // Abbreviation is not a part of the CommonMark specification, but I will just assume it to behave
                // similar to the reference specification
                $key = \trim(\preg_replace('/\s+/', ' ', $m[1]));
                // Queue the abbreviation data to be used later
                $title = \trim(\substr($v[1], \strlen($m[0]) + 2));
                if (isset($lot[$v[0]][$key])) {
                    continue;
                }
                $lot[$v[0]][$key] = $title;
                continue;
            }
            // `[asdf]:` or `[^asdf]:`
            if (0 === \strpos($v[1], '[') && \preg_match('/' . r('[]', true) . '/', $v[1], $m) && ':' === \substr($v[1], \strlen($m[0]), 1)) {
                // `[^asdf]:`
                if (0 === \strpos($m[1], '^')) {
                    // Remove note block from the structure
                    unset($blocks[$k]);
                    $key = \trim(\strtolower(\preg_replace('/\s+/', ' ', \substr($m[1], 1))));
                    $note = \substr($v[1], \strlen($m[0]) + 1);
                    $d = \str_repeat(' ', \strlen($m[0]) + 1 + \strspn($note, ' '));
                    if (isset($lot[$v[0]][$key])) {
                        continue;
                    }
                    if ("" === \trim($note)) {
                        $v = ['p', row($v[1], $lot)[0], [], $v[3]];
                        continue;
                    }
                    if (false !== \strpos(" \n", $note[0])) {
                        $note = \substr($note, 1);
                    }
                    // Remove indent(s)
                    [$a, $b] = \explode("\n", $note . "\n", 2);
                    $note = \trim(\strtr("\n" . $a . "\n" . $b, [
                        "\n" . \str_repeat(' ', \strspn(\trim($b, "\n"), ' ')) => "\n"
                    ]), "\n");
                    // Queue the note data to be used later
                    $lot_of_note = [$lot[0], $lot[1]];
                    $lot[$v[0]][$key] = rows($note, $lot_of_note, $level + 1)[0];
                    continue;
                }
                $data = $key = $link = $title = null;
                if (\preg_match('/^\s*(' . q('<>') . '|\S+?)(?>\s+(' . q('"') . '|' . q("'") . '|' . q('()') . '))?(?>\s*(' . q('{}', false, q('"') . '|' . q("'")) . '))?\s*$/', \substr($v[1], \strlen($m[0]) + 1), $n)) {
                    // Remove reference block from the structure
                    unset($blocks[$k]);
                    // <https://spec.commonmark.org/0.30#matches>
                    $key = \trim(\strtolower(\preg_replace('/\s+/', ' ', $m[1])));
                    // <https://spec.commonmark.org/0.30#example-204>
                    if (isset($lot[$v[0]][$key])) {
                        continue;
                    }
                    if ($link = $n[1] ?? "") {
                        if ('<' === $link[0] && '>' === \substr($link, -1)) {
                            $link = \substr($link, 1, -1);
                            // <https://spec.commonmark.org/0.30#example-490>
                            // <https://spec.commonmark.org/0.30#example-492>
                            // <https://spec.commonmark.org/0.30#example-493>
                            if (false !== \strpos($link, "\n") || '\\' === \substr($link, -1) || 0 === \strpos($link, '<')) {
                                $v[0] = 'p';
                                $v[1] = row($v[1], $lot)[0];
                                continue;
                            }
                        }
                    }
                    if ("" !== ($title = $n[2] ?? "")) {
                        $a = $title[0];
                        $b = \substr($title, -1);
                        if (('"' === $a && '"' === $b || "'" === $a && "'" === $b || '(' === $a && ')' === $b) && \preg_match('/^' . q($a . $b) . '$/', $title)) {
                            $title = v(d(\substr($title, 1, -1)));
                        } else {
                            $v[0] = 'p';
                            $v[1] = row($v[1], $lot)[0];
                            continue;
                        }
                    } else {
                        $title = null;
                    }
                    if ($data = $n[3] ?? []) {
                        $data = a($n[3], true);
                    }
                    if (!l($link)) {
                        $data['rel'] = $data['rel'] ?? 'nofollow';
                        $data['target'] = $data['target'] ?? '_blank';
                    }
                    // Queue the reference data to be used later
                    $lot[$v[0]][$key] = [u(v($link)), $title, $data];
                    continue;
                }
                $v[0] = 'p';
                $v[1] = row($v[1], $lot)[0];
                continue;
            }
        }
        $blocks = \array_values($blocks);
        foreach ($blocks as $k => &$v) {
            if (false === $v[0]) {
                continue;
            }
            $next = $blocks[$k + 1] ?? 0;
            if ('p' === $v[0] && \is_array($next) && 'dl' === $next[0] && \strlen($next[1]) > 2 && ':' === $next[1][0] && ' ' === $next[1][1]) {
                $v[0] = 'dl';
                $v[1] .= "\n\n" . $next[1];
                $v[4] = $next[4];
                unset($blocks[$k + 1]);
                // Parse the definition list later
                continue;
            }
            if ('dl' === $v[0]) {
                // Must be a definition value without its term(s). Fall it back to the default block type!
                if (\strlen($v[1]) > 2 && ':' === $v[1][0] && ' ' === $v[1][1]) {
                    $v = ['p', $v[1], [], $v[3]];
                }
                // Parse the definition list later
                continue;
            }
            if ('blockquote' === $v[0]) {
                $v[1] = \substr(\strtr($v[1], ["\n>" => "\n"]), 1);
                $v[1] = \substr(\strtr("\n" . $v[1], ["\n " => "\n"]), 1); // Remove space
                $v[1] = rows($v[1], $lot, $level + 1)[0];
                continue;
            }
            if ('figure' === $v[0]) {
                $row = row(\trim($v[1], "\n"), $lot)[0];
                // The image syntax doesn’t seem to appear alone on a single line
                if (\is_array($row) && \count($row) > 1) {
                    if (!empty($v[4])) {
                        [$a, $b] = \explode("\n\n", $v[4] . "\n\n", 2);
                        $v = [false, \array_merge([['p', row(\trim($v[1] . "\n" . $a, "\n"), $lot)[0], [], 0]], rows(\trim($b, "\n"), $lot, $level + 1)[0]), [], $v[3]];
                        continue;
                    }
                    $v = ['p', $row, [], $v[3]];
                    continue;
                }
                if (!empty($v[4])) {
                    $b = \rtrim($v[4], "\n");
                    $caption = rows($b, $lot, $level + 1)[0];
                    if (0 !== \strpos($b, "\n") && false === \strpos($b, "\n\n") && \is_array($test = \reset($caption)) && 'p' === $test[0]) {
                        $caption = $test[1];
                    }
                    $row[] = ['figcaption', $caption, [], 0];
                }
                if (isset($row[0][0]) && 'img' === $row[0][0]) {
                    $row[0][3] = 0; // Mark as block
                }
                $v[1] = $row;
                unset($v[4]);
                continue;
            }
            if ('hr' === $v[0]) {
                $v[1] = false;
                continue;
            }
            if ('h' === $v[0][0]) {
                if ('#' === $v[4][0]) {
                    $v[1] = \trim(\substr($v[1], $v[4][1]));
                    // `# asdf {asdf=asdf}`
                    // `# asdf ## {asdf=asdf}`
                    if (\strpos($v[1], '{') > 0 && \preg_match('/' . q('{}', true, q('"') . '|' . q("'")) . '\s*$/', $v[1], $m, \PREG_OFFSET_CAPTURE)) {
                        if ("" !== \trim($m[1][0]) && '\\' !== \substr($v[1], $m[0][1] - 1, 1)) {
                            $v[1] = \rtrim(\substr($v[1], 0, $m[0][1]));
                            $v[2] = \array_replace($v[2], a(\rtrim($m[0][0]), true));
                        }
                    }
                    if ("" !== $v[1]) {
                        // Remove all decorative `#` at the end of the header text, but consider them as part of the
                        // header text if they are not preceded by a space, right after the actual header text
                        while ('#' === \substr($v[1], -1)) {
                            if (false === \strpos(' #', \substr($v[1], -2, 1))) {
                                break;
                            }
                            $v[1] = \substr($v[1], 0, -1);
                        }
                        $v[1] = \rtrim($v[1]);
                    }
                    // `# asdf {asdf=asdf} ##`
                    if (\strpos($v[1], '{') > 0 && \preg_match('/' . q('{}', true, q('"') . '|' . q("'")) . '\s*$/', $v[1], $m, \PREG_OFFSET_CAPTURE)) {
                        if ("" !== \trim($m[1][0]) && '\\' !== \substr($v[1], $m[0][1] - 1, 1)) {
                            $v[1] = \rtrim(\substr($v[1], 0, $m[0][1]));
                            $v[2] = \array_replace($v[2], a(\rtrim($m[0][0]), true));
                        }
                    }
                } else if (false !== \strpos('-=', $v[4][0])) {
                    if (false !== ($n = \strpos($v[1], "\n" . $v[4][0]))) {
                        $v[1] = \substr($v[1], 0, $n);
                        // `asdf {asdf=asdf}\n====`
                        if (\strpos($v[1], '{') > 0 && \preg_match('/' . q('{}', true, q('"') . '|' . q("'")) . '\s*$/', $v[1], $m, \PREG_OFFSET_CAPTURE)) {
                            if ("" !== \trim($m[1][0]) && '\\' !== \substr($v[1], $m[0][1] - 1, 1)) {
                                $v[1] = \rtrim(\substr($v[1], 0, $m[0][1]));
                                $v[2] = \array_replace($v[2], a(\rtrim($m[0][0]), true));
                            }
                        }
                    } else {
                        $v = ['p', $v[1], [], $v[3]];
                    }
                }
                $v[1] = row($v[1], $lot)[0];
                continue;
            }
            if ('ol' === $v[0]) {
                $list = \preg_split('/\n+(?=\d+[).](\s|$))/', $v[1]);
                $list_is_tight = false === \strpos($v[1], "\n\n");
                foreach ($list as &$vv) {
                    $vv = \substr(\strtr($vv, ["\n" . \str_repeat(' ', $v[4][1]) => "\n"]), \strspn($vv, '0123456789') + 2); // Remove indent(s)
                    $vv = rows($vv, $lot, $level + 1)[0];
                    if ($list_is_tight && \is_array($vv)) {
                        foreach ($vv as &$vvv) {
                            if (\is_array($vvv) && 'p' === $vvv[0]) {
                                $vvv[0] = false;
                            }
                        }
                        unset($vvv);
                    }
                    $vv = ['li', m($vv), [], 0];
                }
                unset($vv);
                $v[1] = $list;
                $v[4][] = !$list_is_tight;
                continue;
            }
            if ('pre' === $v[0]) {
                if (2 === $v[4][0] || 3 === $v[4][0]) {
                    $v[1] = \substr(\strstr($v[1], "\n"), 1);
                    if ("" !== ($fence = \trim(\strrchr($v[1], "\n") ?: $v[1], "\n"))) {
                        if (['`' => 2, '~' => 3][$fence[0]] === $v[4][0] && \strlen($fence) === $v[4][1]) {
                            $v[1] = \substr($v[1], 0, -$v[4][1]);
                        }
                    }
                    if ("\n" === \substr($v[1], -1) && "" !== \trim($v[1])) {
                        $v[1] = \substr($v[1], 0, -1);
                    }
                }
                $v[1] = [['code', e($v[1]), $v[2]]];
                $v[2] = [];
                continue;
            }
            if ('table' === $v[0]) {
                $table = [
                    ['thead', [['tr', [], [], 0]], 0],
                    ['tbody', [], [], 0]
                ];
                $rows = \explode("\n", \trim($v[1], "\n"));
                $headers = \trim(\array_shift($rows) ?? "", ' |');
                $styles = \trim(\array_shift($rows) ?? "", ' |');
                // Header-less table
                if (\strspn($headers, ' -:|') === \strlen($headers)) {
                    \array_unshift($rows, $styles);
                    $styles = $headers;
                    $headers = "";
                }
                // Missing table header line
                if ("" === $styles) {
                    $v = ['p', row($v[1], $lot)[0], [], $v[3]];
                    continue;
                }
                // Invalid table header line
                if (\strspn($styles, ' -:|') !== \strlen($styles)) {
                    $v = ['p', row($v[1], $lot)[0], [], $v[3]];
                    continue;
                }
                $styles = \explode('|', $styles);
                $styles_count = \count($styles);
                foreach ($styles as &$vv) {
                    $vv = \trim($vv);
                    if (':' === $vv[0] && ':' === \substr($vv, -1)) {
                        $vv = 'center';
                        continue;
                    }
                    if (':' === $vv[0]) {
                        $vv = 'left';
                        continue;
                    }
                    if (':' === \substr($vv, -1)) {
                        $vv = 'right';
                        continue;
                    }
                    $vv = null;
                }
                unset($vv);
                $lot['is']['table'] = 1;
                if ("" !== $headers) {
                    $th = [];
                    if (\is_array($headers = row($headers, $lot)[0])) {
                        $i = 0;
                        foreach ($headers as $vv) {
                            $th[$i] = $th[$i] ?? ['th', [], [], 0];
                            if (\is_array($vv)) {
                                if (false === $vv[0] && '|' === $vv[1]) {
                                    $i += 1;
                                    continue;
                                }
                                $th[$i][1][] = $vv;
                                continue;
                            }
                            $th[$i][1][] = $vv;
                        }
                        foreach ($th as $kk => &$vv) {
                            $vv[1] = m($vv[1]);
                            if (\is_array($vv[1])) {
                                if (\is_string(\reset($vv[1]))) {
                                    $vv[1][$kk = \key($vv[1])] = \ltrim($vv[1][$kk]);
                                }
                                if (\is_string(\end($vv[1]))) {
                                    $vv[1][$kk = \key($vv[1])] = \rtrim($vv[1][$kk]);
                                }
                            } else if (\is_string($vv[1])) {
                                $vv[1] = \trim($vv[1]);
                            }
                            if (isset($styles[$kk])) {
                                $vv[2]['style'] = 'text-align: ' . $styles[$kk] . ';';
                            }
                        }
                        unset($vv);
                    } else {
                        $th[] = ['th', \trim($headers), [], 0];
                    }
                    $table[0][1][0][1] = \array_pad(\array_slice($th, 0, $styles_count), $styles_count, ['th', "", [], 0]);
                }
                foreach ($rows as $row) {
                    $td = [];
                    if (\is_array($row = row(\trim($row, ' |'), $lot)[0])) {
                        $i = 0;
                        foreach ($row as $vv) {
                            $td[$i] = $td[$i] ?? ['td', [], [], 0];
                            if (\is_array($vv)) {
                                if (false === $vv[0] && '|' === $vv[1]) {
                                    $i += 1;
                                    continue;
                                }
                                $td[$i][1][] = $vv;
                                continue;
                            }
                            $td[$i][1][] = $vv;
                        }
                        foreach ($td as $kk => &$vv) {
                            $vv[1] = m($vv[1]);
                            if (\is_array($vv[1])) {
                                if (\is_string(\reset($vv[1]))) {
                                    $vv[1][$kk = \key($vv[1])] = \ltrim($vv[1][$kk]);
                                }
                                if (\is_string(\end($vv[1]))) {
                                    $vv[1][$kk = \key($vv[1])] = \rtrim($vv[1][$kk]);
                                }
                            } else if (\is_string($vv[1])) {
                                $vv[1] = \trim($vv[1]);
                            }
                            if (isset($styles[$kk])) {
                                $vv[2]['style'] = 'text-align: ' . $styles[$kk] . ';';
                            }
                        }
                        unset($vv);
                    } else {
                        $td[] = ['td', \trim($row), [], 0];
                    }
                    $table[1][1][] = ['tr', \array_pad(\array_slice($td, 0, $styles_count), $styles_count, ['td', "", [], 0]), [], 0];
                }
                unset($lot['is']['table']);
                // Remove empty `<thead>`
                if (empty($table[0][1][0][1])) {
                    unset($table[0]);
                }
                // Remove empty `<tbody>`
                if (empty($table[1][1])) {
                    unset($table[1]);
                }
                if (!empty($v[4])) {
                    $b = \rtrim($v[4], "\n");
                    $caption = rows($b, $lot, $level + 1)[0];
                    if (0 !== \strpos($b, "\n") && false === \strpos($b, "\n\n") && \is_array($test = \reset($caption)) && 'p' === $test[0]) {
                        $caption = $test[1];
                    }
                    \array_unshift($table, ['caption', $caption, [], 0]);
                }
                $v[1] = $table;
                unset($v[4]);
                continue;
            }
            if ('ul' === $v[0]) {
                $list = \preg_split('/\n+(?=[*+-](\s|$))/', $v[1]);
                $list_is_tight = false === \strpos($v[1], "\n\n");
                foreach ($list as &$vv) {
                    $vv = \substr(\strtr($vv, ["\n" . \str_repeat(' ', $v[4][1]) => "\n"]), 2); // Remove indent(s)
                    $vv = rows($vv, $lot, $level + 1)[0];
                    if ($list_is_tight && \is_array($vv)) {
                        foreach ($vv as &$vvv) {
                            if (\is_array($vvv) && 'p' === $vvv[0]) {
                                $vvv[0] = false;
                            }
                        }
                        unset($vvv);
                    }
                    $vv = ['li', m($vv), [], 0];
                }
                unset($vv);
                $v[1] = $list;
                $v[4][] = !$list_is_tight;
                continue;
            }
            if (\is_string($v[1])) {
                $v[1] = row(\rtrim($v[1]), $lot)[0];
            }
        }
        foreach ($blocks as &$v) {
            // Late definition list parsing
            if ('dl' === $v[0]) {
                $list = \preg_split('/\n+(?=:\s|[^:\s])/', $v[1]);
                $list_is_tight = false === \strpos($v[1], "\n\n");
                foreach ($list as &$vv) {
                    if (\strlen($vv) > 2 && ':' === $vv[0] && ' ' === $vv[1]) {
                        $vv = \substr(\strtr($vv, ["\n" . \str_repeat(' ', $v[4][1]) => "\n"]), 2); // Remove indent(s)
                        $vv = rows($vv, $lot, $level + 1)[0];
                        if ($list_is_tight && \is_array($vv)) {
                            foreach ($vv as &$vvv) {
                                if (\is_array($vvv) && 'p' === $vvv[0]) {
                                    $vvv[0] = false;
                                }
                            }
                            unset($vvv);
                        }
                        $vv = ['dd', m($vv), [], 0];
                        continue;
                    }
                    $vv = ['dt', row($vv)[0], [], 0];
                }
                unset($vv);
                $v[1] = $list;
                $v[4][] = !$list_is_tight;
            }
        }
        unset($v);
        $blocks = \array_values($blocks);
        if (!empty($lot[2]) && 0 === $level) {
            $notes = ['div', [
                ['hr', false, [], 0, '-'],
                ['ol', [], [], 0, [0, 1, '.']]
            ], [
                'role' => 'doc-endnotes'
            ], 0];
            foreach ($lot[2] as $k => $v) {
                if (!isset($lot['notes'][$k])) {
                    continue;
                }
                if (\is_array($v) && \is_array($last = \array_pop($v))) {
                    if ('p' === $last[0]) {
                        $last[1] = (array) $last[1];
                        for ($i = 0, $j = $lot['notes'][$k]; $i < $j; ++$i) {
                            $last[1][] = ['&', '&#160;', [], -1];
                            $last[1][] = ['a', [['&', '&#8617;', [], -1]], [
                                'href' => '#from:' . $k . ($i > 0 ? '.' . ($i + 1) : ""),
                                'role' => 'doc-backlink'
                            ], -1, [false, ""]];
                        }
                        $v[] = $last;
                    } else {
                        $v[] = $last;
                        $p = ['p', [], [], 0];
                        for ($i = 0, $j = $lot['notes'][$k]; $i < $j; ++$i) {
                            if ($i > 0) {
                                $p[1][] = ['&', '&#160;', [], -1];
                            }
                            $p[1][] = ['a', [['&', '&#8617;', [], -1]], [
                                'href' => '#from:' . $k . ($i > 0 ? '.' . ($i + 1) : ""),
                                'role' => 'doc-backlink'
                            ], -1, [false, ""]];
                        }
                        $v[] = $p;
                    }
                }
                $notes[1][1][1][] = ['li', $v, [
                    'id' => 'to:' . $k,
                    'role' => 'doc-endnote'
                ], 0];
            }
            if ($notes[1][1][1]) {
                $blocks['notes'] = $notes;
            }
            unset($lot['notes']);
        }
        unset($lot['is']);
        return [$blocks, $lot];
    }
    function s(array $data): ?string {
        [$t, $c, $a] = $data;
        if (false === $t) {
            if (\is_array($c)) {
                $out = "";
                foreach ($c as $v) {
                    $out .= \is_array($v) ? s($v) : $v;
                }
                return $out;
            }
            return $c;
        }
        if (\is_int($t)) {
            return "";
        }
        if ('&' === $t) {
            return e(d($c));
        }
        $out = '<' . $t;
        if (!empty($a) && \is_array($a)) {
            \ksort($a);
            foreach ($a as $k => $v) {
                if (false === $v || null === $v) {
                    continue;
                }
                $out .= ' ' . $k . (true === $v ? "" : '="' . e($v) . '"');
            }
        }
        if (false !== $c) {
            $out .= '>';
            if (\is_array($c)) {
                foreach ($c as $v) {
                    $out .= \is_array($v) ? s($v) : $v;
                }
            } else {
                $out .= $c;
            }
            $out .= '</' . $t . '>';
        } else {
            $out .= ' />';
        }
        return "" !== $out ? $out : null;
    }
    // <https://stackoverflow.com/a/6059053/1163000>
    function u(?string $v): string {
        return \strtr(\rawurlencode(d($v)), [
            '%21' => '!',
            '%23' => '#',
            '%24' => '$',
            '%26' => '&',
            '%27' => "'",
            '%28' => '(',
            '%29' => ')',
            '%2A' => '*',
            '%2B' => '+',
            '%2C' => ',',
            '%2D' => '-',
            '%2E' => '.',
            '%2F' => '/',
            '%3A' => ':',
            '%3B' => ';',
            '%3D' => '=',
            '%3F' => '?',
            '%40' => '@',
            '%5B' => '[',
            '%5D' => ']',
            '%5F' => '_',
            '%7E' => '~'
        ]);
    }
    // <https://spec.commonmark.org/0.30#example-12>
    function v(?string $value): string {
        return null !== $value ? \strtr($value, [
            "\\'" => "'",
            "\\\\" => "\\",
            '\!' => '!',
            '\"' => '"',
            '\#' => '#',
            '\$' => '$',
            '\%' => '%',
            '\&' => '&',
            '\(' => '(',
            '\)' => ')',
            '\*' => '*',
            '\+' => '+',
            '\,' => ',',
            '\-' => '-',
            '\.' => '.',
            '\/' => '/',
            '\:' => ':',
            '\;' => ';',
            '\<' => '<',
            '\=' => '=',
            '\>' => '>',
            '\?' => '?',
            '\@' => '@',
            '\[' => '[',
            '\]' => ']',
            '\^' => '^',
            '\_' => '_',
            '\`' => '`',
            '\{' => '{',
            '\|' => '|',
            '\}' => '}',
            '\~' => '~'
        ]) : "";
    }
}