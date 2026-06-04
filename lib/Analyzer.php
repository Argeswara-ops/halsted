<?php
/**
 * CodePulse Static Code Analyzer Engine
 * Core Logic for parsing functions, tokenizing, and calculating Halstead & McCabe metrics.
 */

// Match braces to extract function body
if (!function_exists('getFunctionBody')) {
    function getFunctionBody($code, $start_pos) {
        $length = strlen($code);
        $brace_count = 0;
        $body_start = -1;
        for ($i = $start_pos; $i < $length; $i++) {
            if ($code[$i] === '{') {
                $body_start = $i;
                $brace_count = 1;
                break;
            }
        }
        if ($body_start === -1) return '';
        
        for ($i = $body_start + 1; $i < $length; $i++) {
            if ($code[$i] === '"' || $code[$i] === "'") {
                $quote = $code[$i];
                $i++;
                while ($i < $length && $code[$i] !== $quote) {
                    if ($code[$i] === '\\') $i++;
                    $i++;
                }
                continue;
            }
            if ($code[$i] === '/' && $i + 1 < $length && $code[$i+1] === '/') {
                while ($i < $length && $code[$i] !== "\n") $i++;
                continue;
            }
            if ($code[$i] === '/' && $i + 1 < $length && $code[$i+1] === '*') {
                $i += 2;
                while ($i + 1 < $length && !($code[$i] === '*' && $code[$i+1] === '/')) $i++;
                $i++;
                continue;
            }
            if ($code[$i] === '{') {
                $brace_count++;
            } elseif ($code[$i] === '}') {
                $brace_count--;
                if ($brace_count === 0) {
                    return substr($code, $body_start, $i - $body_start + 1);
                }
            }
        }
        return substr($code, $body_start);
    }
}

// Calculate Cyclomatic Complexity (McCabe)
if (!function_exists('calculateMcCabe')) {
    function calculateMcCabe($function_body) {
        // Strip comments
        $comment_patterns = array('/\\/\\*[\\s\\S]*?\\*\\//', '/\\/\\/.*$/m');
        $clean_body = preg_replace($comment_patterns, '', $function_body);

        $cc = 1;
        // Keywords representing decisions
        $keywords = ['if', 'elseif', 'else if', 'while', 'for', 'foreach', 'case', 'catch'];
        foreach ($keywords as $kw) {
            $cc += preg_match_all('/\b' . preg_quote($kw, '/') . '\b/i', $clean_body);
        }
        // Logical operators representing decisions
        $operators = ['&&', '||', '??', '?'];
        foreach ($operators as $op) {
            $cc += preg_match_all('/' . preg_quote($op, '/') . '/', $clean_body);
        }
        return $cc;
    }
}

// Analyze the uploaded project files (single or multiple)
if (!function_exists('analyzeProject')) {
    function analyzeProject($files) {
        $total_files = count($files);
        $all_functions = [];
        $files_report = [];
        
        $global_operators = [
            '->', '::', '++', '--', '+=', '-=', '*=', '/=', '.=', '===', '==', '!==', '!=', 
            '<=', '>=', '&&', '||', '+', '-', '*', '/', '%', '=', '!', '<', '>', '&', '|', '^',
            '?', ':', ';', ',', '(', ')', '[', ']', '{', '}'
        ];
        usort($global_operators, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        $escaped_ops = array_map(function($op) { return preg_quote($op, '#'); }, $global_operators);
        $operator_pattern = '#' . implode('|', $escaped_ops) . '#';

        $total_n1 = [];
        $total_n2 = [];
        $total_N1 = 0;
        $total_N2 = 0;
        $total_lines = 0;
        
        foreach ($files as $fileName => $code) {
            $lines_count = count(explode("\n", $code));
            $total_lines += $lines_count;

            // Strip comments for token counts
            $comment_patterns = array('/\\/\\*[\\s\\S]*?\\*\\//', '/\\/\\/.*$/m');
            $clean_code = preg_replace($comment_patterns, '', $code);
            
            // Halstead Operators
            $file_operators = [];
            if (preg_match_all($operator_pattern, $clean_code, $op_matches)) {
                $file_operators = $op_matches[0];
            }
            $total_N1 += count($file_operators);
            $total_n1 = array_merge($total_n1, $file_operators);

            // Halstead Operands
            $operand_text = preg_replace($operator_pattern, ' ', $clean_code);
            $file_operands = [];
            if (preg_match_all('/\\$[a-zA-Z_][a-zA-Z0-9_]*|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|\\b[0-9]+\\b|\\b[a-zA-Z_][a-zA-Z0-9_]*\\b/', $operand_text, $operand_matches)) {
                $file_operands = $operand_matches[0];
            }
            $total_N2 += count($file_operands);
            $total_n2 = array_merge($total_n2, $file_operands);

            // Scan functions for McCabe
            $file_functions = [];
            
            // PHP functions: function name(...) or public function name(...) etc.
            preg_match_all('/(?:(?:public|private|protected|static)\s+)*function\s+([a-zA-Z0-9_]+)\s*\(/i', $code, $php_matches, PREG_OFFSET_CAPTURE);
            foreach ($php_matches[1] as $match) {
                $file_functions[] = [
                    'name' => $match[0],
                    'offset' => $match[1],
                    'type' => 'PHP Function'
                ];
            }
            
            // JS Arrow functions: const name = () =>
            preg_match_all('/(?:const|let|var)\s+([a-zA-Z0-9_]+)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>/i', $code, $js_arrow_matches, PREG_OFFSET_CAPTURE);
            foreach ($js_arrow_matches[1] as $match) {
                $file_functions[] = [
                    'name' => $match[0],
                    'offset' => $match[1],
                    'type' => 'JS Arrow Function'
                ];
            }

            // JS methods: methodName() {
            preg_match_all('/(?<!function\s)\b([a-zA-Z0-9_]+)\s*\([^)]*\)\s*\{/i', $code, $js_method_matches, PREG_OFFSET_CAPTURE);
            foreach ($js_method_matches[1] as $match) {
                $func_name = $match[0];
                if (in_array(strtolower($func_name), ['if', 'while', 'switch', 'for', 'catch', 'foreach', 'elseif'])) {
                    continue;
                }
                $already_added = false;
                foreach ($file_functions as $f) {
                    if (abs($f['offset'] - $match[1]) < 20) {
                        $already_added = true;
                        break;
                    }
                }
                if (!$already_added) {
                    $file_functions[] = [
                        'name' => $func_name,
                        'offset' => $match[1],
                        'type' => 'Method'
                    ];
                }
            }

            // Sort by position
            usort($file_functions, function($a, $b) {
                return $a['offset'] - $b['offset'];
            });

            // Compute function details
            $parsed_funcs = [];
            foreach ($file_functions as $func) {
                $body = getFunctionBody($code, $func['offset']);
                $cc = calculateMcCabe($body);
                
                $pre_code = substr($code, 0, $func['offset']);
                $start_line = count(explode("\n", $pre_code));
                $body_lines = count(explode("\n", $body));
                $end_line = $start_line + $body_lines - 1;

                $parsed_funcs[] = [
                    'name' => $func['name'],
                    'file' => $fileName,
                    'start_line' => $start_line,
                    'end_line' => $end_line,
                    'complexity' => $cc,
                    'body' => $body
                ];
                $all_functions[] = $parsed_funcs[count($parsed_funcs)-1];
            }

            // If no functions found, treat the whole file as a single default main block
            if (empty($parsed_funcs)) {
                $cc = calculateMcCabe($clean_code);
                $parsed_funcs[] = [
                    'name' => '(main script)',
                    'file' => $fileName,
                    'start_line' => 1,
                    'end_line' => $lines_count,
                    'complexity' => $cc,
                    'body' => $code
                ];
                $all_functions[] = $parsed_funcs[0];
            }

            // Calculate average complexity for this file
            $file_cc_sum = 0;
            foreach ($parsed_funcs as $pf) {
                $file_cc_sum += $pf['complexity'];
            }
            $file_avg_cc = count($parsed_funcs) > 0 ? $file_cc_sum / count($parsed_funcs) : 1;

            // Calculate Halstead metrics per file
            $file_unique_ops = array_unique($file_operators);
            $file_unique_ords = array_unique($file_operands);
            
            $fn1 = count($file_unique_ops);
            $fn2 = count($file_unique_ords);
            $fN1 = count($file_operators);
            $fN2 = count($file_operands);
            
            $fN = $fN1 + $fN2;
            $fn = $fn1 + $fn2;
            $fV = $fN * ($fn > 0 ? log($fn, 2) : 0);
            $fD = ($fn2 > 0) ? ($fn1 / 2) * ($fN2 / $fn2) : 0;
            $fE = $fD * $fV;
            $fB = $fV / 3000;

            $files_report[] = [
                'name' => $fileName,
                'lines' => $lines_count,
                'functions_count' => count($parsed_funcs),
                'avg_complexity' => round($file_avg_cc, 1),
                'operators_count' => count($file_operators),
                'operands_count' => count($file_operands),
                'volume' => round($fV, 2),
                'difficulty' => round($fD, 2),
                'bugs' => round($fB, 4)
            ];
        }

        // Global Halstead metrics
        $unique_ops = array_unique($total_n1);
        $unique_ords = array_unique($total_n2);
        
        $n1 = count($unique_ops);
        $n2 = count($unique_ords);
        $N1 = $total_N1;
        $N2 = $total_N2;

        $N = $N1 + $N2;
        $n = $n1 + $n2;
        $V = $N * ($n > 0 ? log($n, 2) : 0);
        $D = ($n2 > 0) ? ($n1 / 2) * ($N2 / $n2) : 0;
        $E = $D * $V;
        $T = $E / 18;
        $B = $V / 3000;

        $cc_sum = 0;
        $high_risk_count = 0;
        $cc_breakdown = [
            'safe' => 0,
            'moderate' => 0,
            'high' => 0
        ];

        foreach ($all_functions as $f) {
            $cc_sum += $f['complexity'];
            if ($f['complexity'] <= 10) {
                $cc_breakdown['safe']++;
            } elseif ($f['complexity'] <= 20) {
                $cc_breakdown['moderate']++;
            } else {
                $cc_breakdown['high']++;
                $high_risk_count++;
            }
        }

        $avg_complexity = count($all_functions) > 0 ? $cc_sum / count($all_functions) : 0;

        return [
            'total_files' => $total_files,
            'total_functions' => count($all_functions),
            'avg_complexity' => round($avg_complexity, 1),
            'high_risk_functions' => $high_risk_count,
            'lines' => $total_lines,
            'n1' => $n1, 'n2' => $n2, 'N1' => $N1, 'N2' => $N2,
            'N' => $N, 'n' => $n, 'V' => round($V, 2), 'D' => round($D, 2),
            'E' => round($E, 2), 'T' => round($T, 2), 'B' => round($B, 4),
            'unique_operators_list' => array_values($unique_ops),
            'unique_operands_list' => array_values($unique_ords),
            'functions' => $all_functions,
            'files_report' => $files_report,
            'cc_breakdown' => $cc_breakdown
        ];
    }
}
?>
