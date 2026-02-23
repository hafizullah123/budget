<?php
require_once '../config/database.php';
require_once '../includes/budget_functions.php';
require_once '../includes/init_session.php';

$conn = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        $expense_type = $_POST['expense_type'] ?? '';
        
        // Validate budget limits
        list($budget_errors, $code_amounts) = validateBudgetLimits($conn, $_POST, $expense_type);
        
        if (!empty($budget_errors)) {
            throw new Exception(implode("<br>", $budget_errors));
        }

        /* ---------- expense_vouchers ---------- */
        $stmt = $conn->prepare("
            INSERT INTO expense_vouchers
            (expense_type_code, expense_type_desc, voucher_number, voucher_date, year,
             system_number, system_date, sgtas_number, scan_number,
             asaar, currency, admin_code, total_debit, total_credit, payable_amount, payment_method)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $total_debit    = $_POST['total_debit'] ?? 0;
        $total_credit   = $_POST['total_credit'] ?? 0;
        $payable_amount = $_POST['payable_amount'] ?? 0;
        $currency = 'AFN';
        $admin_code = '194000';
        $expense_type_code = $_POST['expense_type'] ?? '';

        $stmt->bind_param(
            "isssssssssssddds",
            $expense_type_code,
            $_POST['expense_type_desc'],
            $_POST['voucher_number'],
            $_POST['voucher_date'],
            $_POST['year'],
            $_POST['system_number'],
            $_POST['system_date'],
            $_POST['sgtas_number'],
            $_POST['scan_number'],
            $_POST['asaar'],
            $currency,
            $admin_code,
            $total_debit,
            $total_credit,
            $payable_amount,
            $_POST['payment_method']
        );
        $stmt->execute();
        $voucher_id = $conn->insert_id;
        $stmt->close();

        /* ---------- expense_voucher_items ---------- */
        $stmt = $conn->prepare("
            INSERT INTO expense_voucher_items
            (voucher_id, details, general_code, sub_code, debit, credit)
            VALUES (?,?,?,?,?,?)
        ");
        
        foreach ($_POST['details'] as $i => $detail) {
            if (trim($detail) === '') continue;

            $debit  = $_POST['debit'][$i] ?? 0;
            $credit = $_POST['credit'][$i] ?? 0;
            $general_code = $_POST['general_code'][$i];
            $sub_code = $_POST['sub_code'][$i];

            $stmt->bind_param(
                "isssdd",
                $voucher_id,
                $detail,
                $general_code,
                $sub_code,
                $debit,
                $credit
            );
            $stmt->execute();
        }
        $stmt->close();

        /* ---------- expense_recipients ---------- */
        if (!empty($_POST['recipient_name'])) {
            $stmt = $conn->prepare("
                INSERT INTO expense_recipients
                (voucher_id, recipient_name, payer_recipient_number, system_recipient_number)
                VALUES (?,?,?,?)
            ");
            $stmt->bind_param(
                "isss",
                $voucher_id,
                $_POST['recipient_name'],
                $_POST['payer_recipient_number'],
                $_POST['system_recipient_number']
            );
            $stmt->execute();
            $stmt->close();
        }

        /* ---------- expense_recipient_banks ---------- */
        if (!empty($_POST['recipient_bank_account'])) {
            $stmt = $conn->prepare("
                INSERT INTO expense_recipient_banks
                (voucher_id, bank_account, invoice_id, bank_name, bank_address)
                VALUES (?,?,?,?,?)
            ");
            $stmt->bind_param(
                "issss",
                $voucher_id,
                $_POST['recipient_bank_account'],
                $_POST['invoice_id'],
                $_POST['bank_name'],
                $_POST['bank_address']
            );
            $stmt->execute();
            $stmt->close();
        }

        /* ---------- UPDATE BUDGET_DETAILS ---------- */
        $debug_output = "<br><strong>Debug Info:</strong><br>";
        $debug_output .= "Expense Type (bab): " . htmlspecialchars($expense_type) . "<br>";
        
        list($total_updated, $update_debug) = updateBudgetDetails($conn, $code_amounts);
        $debug_output .= $update_debug;

        $conn->commit();
        
        $_SESSION['success'] = "✅ سند په بریالیتوب ثبت شو";
        if ($total_updated > 0) {
            $_SESSION['success'] .= "<br>✅ بودیجې تازه شوې ($total_updated کوډونه)";
        }
        
        // Store debug info in session for display
        if (!empty($debug_output)) {
            $_SESSION['debug_info'] = $debug_output;
        }
        
        header("Location: ../index.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "❌ خطا: ".$e->getMessage();
        header("Location: ../index.php");
        exit();
    }
}
?>