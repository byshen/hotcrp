<?php
// cleanhtml.php -- HTML cleaner for CSS prevention
// Copyright (c) 2006-2020 Eddie Kohler; see LICENSE.

class CleanHTML {
    const BADTAGS_IGNORE = 1;

    private $flags;
    private $goodtags;
    private $emptytags;

    static private $g;

    function __construct($flags = 0, $goodtags = null, $emptytags = null) {
        if ($goodtags === null)
            $goodtags = ["a", "abbr", "acronym", "address", "area", "b", "bdi", "bdo", "big", "blockquote", "br", "button", "caption", "center", "cite", "code", "col", "colgroup", "dd", "del", "details", "dir", "div", "dfn", "dl", "dt", "em", "figcaption", "figure", "font", "h1", "h2", "h3", "h4", "h5", "h6", "hr", "i", "img", "ins", "kbd", "label", "legend", "li", "link", "map", "mark", "menu", "menuitem", "meter", "noscript", "ol", "optgroup", "option", "p", "pre", "q", "rp", "rt", "ruby", "s", "samp", "section", "select", "small", "span", "strike", "strong", "sub", "summary", "sup", "table", "tbody", "td", "textarea", "tfoot", "th", "thead", "time", "title", "tr", "tt", "u", "ul", "var", "wbr"];
        if ($emptytags === null)
            $emptytags = ["area", "base", "br", "col", "hr", "img", "input", "link", "meta", "param", "wbr"];
        $this->flags = 0;
        $this->goodtags = is_associative_array($goodtags) ? $goodtags : array_flip($goodtags);
        $this->emptytags = is_associative_array($emptytags) ? $emptytags : array_flip($emptytags);
    }

    private static function _cleanHTMLError(&$err, $etype) {
        $err = "Your HTML code contains $etype. Only HTML content tags are accepted, such as <code>&lt;p&gt;</code>, <code>&lt;strong&gt;</code>, and <code>&lt;h1&gt;</code>, and attributes are restricted.";
        return false;
    }

    /** @param string $t
     * @return string|false */
    function clean($t, &$err = null) {
        $tagstack = array();

        $x = "";
        while ($t !== "") {
            if (($p = strpos($t, "<")) === false) {
                $x .= $t;
                break;
            }
            $x .= substr($t, 0, $p);
            $t = substr($t, $p);

            if (preg_match('/\A<!\[[ie]/', $t)) {
                return self::_cleanHTMLError($err, "an Internet Explorer conditional comment");
            } else if (preg_match('/\A(<!\[CDATA\[.*?)(\]\]>|\z)(.*)\z/s', $t, $m)) {
                $x .= $m[1] . "]]>";
                $t = $m[3];
            } else if (preg_match('/\A<!--.*?(-->|\z)(.*)\z/s', $t, $m)) {
                $t = $m[2];
            } else if (preg_match('/\A<!(\S+)/s', $t, $m)) {
                return self::_cleanHTMLError($err, "<code>$m[1]</code> declarations");
            } else if (preg_match('/\A<\s*([A-Za-z0-9]+)\s*(.*)\z/s', $t, $m)) {
                $tag = strtolower($m[1]);
                if (!isset($this->goodtags[$tag])) {
                    if (!($this->flags & self::BADTAGS_IGNORE))
                        return self::_cleanHTMLError($err, "some <code>&lt;$tag&gt;</code> tag");
                    $x .= "&lt;";
                    $t = substr($t, 1);
                    continue;
                }
                $t = $m[2];
                $x .= "<" . $tag;
                // XXX should sanitize 'id', 'class', 'data-', etc.
                while ($t !== "" && $t[0] !== "/" && $t[0] !== ">") {
                    if (!preg_match(',\A([^\s/<>=\'"]+)\s*(.*)\z,s', $t, $m)) {
                        return self::_cleanHTMLError($err, "garbage <code>" . htmlspecialchars($t) . "</code> within some <code>&lt;$tag&gt;</code> tag");
                    }
                    $attr = strtolower($m[1]);
                    if (strlen($attr) > 2 && $attr[0] === "o" && $attr[1] === "n") {
                        return self::_cleanHTMLError($err, "an event handler attribute in some <code>&lt;$tag&gt;</code> tag");
                    } else if ($attr === "style" || $attr === "script" || $attr === "id") {
                        return self::_cleanHTMLError($err, "<code>$attr</code> attribute in some <code>&lt;$tag&gt;</code> tag");
                    }
                    $x .= " " . $attr;
                    $t = $m[2];
                    if (preg_match(',\A=\s*(\'.*?\'|".*?"|\w+)\s*(.*)\z,s', $t, $m)) {
                        if ($m[1][0] === "'" || $m[1][0] === "\"") {
                            $m[1] = substr($m[1], 1, -1);
                        }
                        $m[1] = html_entity_decode($m[1], ENT_HTML5);
                        if ($attr === "href" && preg_match(',\A\s*javascript\s*:,i', $m[1])) {
                            return self::_cleanHTMLError($err, "<code>href</code> attribute to JavaScript URL");
                        }
                        $x .= "=\"" . htmlspecialchars($m[1]) . "\"";
                        $t = $m[2];
                    }
                }
                if ($t === "") {
                    return self::_cleanHTMLError($err, "an unclosed <code>&lt;$tag&gt;</code> tag");
                } else if ($t[0] === ">") {
                    $t = substr($t, 1);
                    if (isset($this->emptytags[$tag])
                        && !preg_match(',\A\s*<\s*/' . $tag . '\s*>,si', $t))
                        // automagically close empty tags
                        $x .= " />";
                    else {
                        $x .= ">";
                        $tagstack[] = $tag;
                    }
                } else if (preg_match(',\A/\s*>(.*)\z,s', $t, $m)) {
                    $x .= " />";
                    $t = $m[1];
                } else {
                    return self::_cleanHTMLError($err, "garbage in some <code>&lt;$tag&gt;</code> tag");
                }
            } else if (preg_match(',\A<\s*/\s*([A-Za-z0-9]+)\s*>(.*)\z,s', $t, $m)) {
                $tag = strtolower($m[1]);
                if (!isset($this->goodtags[$tag])) {
                    if (!($this->flags & self::BADTAGS_IGNORE)) {
                        return self::_cleanHTMLError($err, "some <code>&lt;/$tag&gt;</code> tag");
                    }
                    $x .= "&lt;";
                    $t = substr($t, 1);
                    continue;
                } else if (empty($tagstack)) {
                    return self::_cleanHTMLError($err, "a extra close tag <code>&lt;/$tag&gt;</code>");
                } else if (($last = array_pop($tagstack)) !== $tag) {
                    return self::_cleanHTMLError($err, "a close tag <code>&lt;/$tag</code> that doesn’t match the open tag <code>&lt;$last</code>");
                }
                $x .= "</$tag>";
                $t = $m[2];
            } else {
                $x .= "&lt;";
                $t = substr($t, 1);
            }
        }

        if (!empty($tagstack)) {
            return self::_cleanHTMLError($err, "unclosed tags, including <code>&lt;$tagstack[0]&gt;</code>");
        }

        return preg_replace('/\r\n?/', "\n", $x);
    }

    /** @param string|list<string> $t
     * @return list<string>|false */
    function clean_all($t, &$err = null) {
        $x = [];
        foreach (is_array($t) ? $t : [$t] as $s) {
            if (is_string($s)
                && ($s = $this->clean($s, $err)) !== false) {
                $x[] = $s;
            } else {
                return false;
            }
        }
        return $x;
    }

    /** @return CleanHTML */
    static function basic() {
        if (!self::$g) {
            self::$g = new CleanHTML;
        }
        return self::$g;
    }

    /** @return string|false */
    static function basic_clean($t, &$err = null) {
        return self::basic()->clean($t, $err);
    }

    /** @return list<string>|false */
    static function basic_clean_all($t, &$err = null) {
        return self::basic()->clean_all($t, $err);
    }
}
