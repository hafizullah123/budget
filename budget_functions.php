<?php
/* ================= BUDGET-RELATED FUNCTIONS ================= */
function fetchBudgetCodes($conn) {
    $query = "
        SELECT general_code, sub_code, budget as remaining_budget, actual as spent,
               (budget + actual) as original_budget,
               CASE 
                   WHEN (budget + actual) > 0 THEN ROUND((actual / (budget + actual)) * 100, 2)
                   ELSE 0 
               END as current_percent
        FROM budget_details 
        ORDER BY general_code ASC, sub_code ASC
    ";
    
    $result = $conn->query($query);
    $all_codes = [];
    $all_general_codes = [];
    
    if ($result) {
        while($row = $result->fetch_assoc()){
            $general_code = $row['general_code'];
            $sub_code = $row['sub_code'];
            $composite_key = $general_code . '_' . $sub_code;
            
            $all_codes[$composite_key] = [
                'general_code' => $general_code,
                'sub_code' => $sub_code,
                'remaining_budget' => $row['remaining_budget'],
                'spent' => $row['spent'],
                'original_budget' => $row['original_budget'],
                'current_percent' => $row['current_percent'],
                'bab' => $sub_code
            ];
            
            if (!in_array($general_code, $all_general_codes)) {
                $all_general_codes[] = $general_code;
            }
        }
    }
    
    return [$all_codes, $all_general_codes];
}

function fetchBabOptions($conn) {
    $query = "SELECT DISTINCT sub_code FROM budget_details ORDER BY sub_code ASC";
    $result = $conn->query($query);
    
    $options = '';
    $debug_info = '';
    
    if (!$result) {
        $debug_info = "Query error: " . $conn->error;
        $options = '<option value="">خطا در دریافت داده ها</option>';
    } elseif ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $bab_value = htmlspecialchars($row['sub_code']);
            $options .= '<option value="' . $bab_value . '">' . $bab_value . '</option>';
        }
    } else {
        $options = '<option value="">-- هیچ داده‌ای یافت نشد --</option>';
        $debug_info = "هیچ داده‌ای در جدول budget_details یافت نشد.";
    }
    
    return [$options, $debug_info];
}

function validateBudgetLimits($conn, $post_data, $expense_type) {
    $budget_errors = [];
    $code_amounts = [];
    
    foreach ($post_data['details'] as $i => $detail) {
        if (trim($detail) === '') continue;

        $debit = $post_data['debit'][$i] ?? 0;
        $general_code = $post_data['general_code'][$i] ?? '';
        
        if ($general_code && $debit > 0) {
            $check_stmt = $conn->prepare("
                SELECT budget, actual 
                FROM budget_details 
                WHERE general_code = ? AND sub_code = ?
            ");
            $check_stmt->bind_param("ss", $general_code, $expense_type);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                $remaining_budget = $row['budget'];
                
                if ($debit > $remaining_budget) {
                    $budget_errors[] = "مبلغ مصرف ($debit) برای کوډ $general_code از بودجه باقیمانده ($remaining_budget) بیشتر است!";
                } else {
                    $key = $general_code . '_' . $expense_type;
                    if (!isset($code_amounts[$key])) {
                        $code_amounts[$key] = [
                            'general_code' => $general_code,
                            'expense_type' => $expense_type,
                            'amount' => 0
                        ];
                    }
                    $code_amounts[$key]['amount'] += $debit;
                }
            } else {
                $budget_errors[] = "کوډ $general_code برای باب $expense_type در سیستم بودجه موجود نیست!";
            }
            $check_stmt->close();
        }
    }
    
    return [$budget_errors, $code_amounts];
}

function updateBudgetDetails($conn, $code_amounts) {
    $debug_output = "<br><strong>Debug Info:</strong><br>";
    $total_updated = 0;
    
    foreach ($code_amounts as $key => $data) {
        $general_code = $data['general_code'];
        $expense_type = $data['expense_type'];
        $amount = $data['amount'];
        
        if ($amount > 0) {
            $check_stmt = $conn->prepare("
                SELECT budget, actual 
                FROM budget_details 
                WHERE general_code = ? AND sub_code = ? 
                FOR UPDATE
            ");
            $check_stmt->bind_param("ss", $general_code, $expense_type);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $row = $check_result->fetch_assoc();
                $current_budget = $row['budget'];
                $current_actual = $row['actual'];
                
                $new_budget = $current_budget - $amount;
                $new_actual = $current_actual + $amount;
                $original_budget = $current_budget + $current_actual;
                
                if ($original_budget > 0) {
                    $new_percent = min(100, ($new_actual / $original_budget) * 100);
                    $new_percent = round($new_percent, 2);
                } else {
                    $new_percent = 0;
                }
                
                $update_stmt = $conn->prepare("
                    UPDATE budget_details 
                    SET budget = ?, actual = ?, percent = ?
                    WHERE general_code = ? AND sub_code = ?
                ");
                
                $update_stmt->bind_param("dddss", 
                    $new_budget, 
                    $new_actual, 
                    $new_percent, 
                    $general_code, 
                    $expense_type
                );
                
                if ($update_stmt->execute()) {
                    $total_updated++;
                    $debug_output .= "Updated: $general_code (Type: $expense_type) - ";
                    $debug_output .= "Old Budget: $current_budget, New Budget: $new_budget, ";
                    $debug_output .= "Amount Used: $amount<br>";
                } else {
                    $debug_output .= "Failed to update: $general_code (Type: $expense_type) - ";
                    $debug_output .= "Error: " . $update_stmt->error . "<br>";
                }
                $update_stmt->close();
            } else {
                $debug_output .= "Not found in budget_details: $general_code (Type: $expense_type)<br>";
            }
            $check_stmt->close();
        }
    }
    
    return [$total_updated, $debug_output];
}
?>