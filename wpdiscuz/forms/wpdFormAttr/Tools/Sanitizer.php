<?php

namespace wpdFormAttr\Tools;

class Sanitizer {

    /**
     * Reads one request variable and sanitizes it.
     *
     * The returned value is still slashed, exactly as WordPress stored it in
     * $_POST/$_GET. That is deliberate and must not be "fixed" inside this
     * helper: most callers hand the value to update_comment_meta() or
     * wp_new_comment(), and both unslash their own input, so unslashing here
     * would strip a level of backslashes off every stored value
     * (C:\path becomes C:path).
     *
     * Wrap the call in wp_unslash() at the call site instead, whenever the
     * value is compared, displayed, or written straight through $wpdb.
     */
    public static function sanitize($action, $variable_name, $filter, $default = "") {
        if ($filter === "FILTER_SANITIZE_STRING" || $filter === "FILTER_SANITIZE_TEXTAREA") {
            $glob = INPUT_POST === $action ? $_POST : $_GET;
            if (key_exists($variable_name, $glob)) {
                // The (string) casts below turn an array into "Array" and raise
                // an "Array to string conversion" warning; non-stringable
                // objects throw an Error. Bail out to $default before that.
                if (!is_scalar($glob[$variable_name])) {
                    return $default;
                }
                if ($filter === "FILTER_SANITIZE_TEXTAREA") {
                    return sanitize_textarea_field((string)$glob[$variable_name]);
                } else {
                    return sanitize_text_field((string)$glob[$variable_name]);
                }
            } else {
                return $default;
            }
        }
        $variable = isset($variable_name) ? filter_input($action, $variable_name, $filter) : '';
        return $variable ? $variable : $default;
    }

}
