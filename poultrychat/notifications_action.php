<?php
include('auth.php');
include('database.php');
include('csrf.php');

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = strtolower($_SESSION['role'] ?? '');

$redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

if ($userId <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit();
    }
    header("Location: index.php");
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_all_read') {
    try {
        if ($userRole === 'admin') {
            $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE (user_id = ? OR user_id IS NULL) AND status = 'unread'");
            $stmt->execute([$userId]);
        } elseif ($userRole === 'integrator') {
            $stmtFindCust = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
            $stmtFindCust->execute([$userId]);
            $userCustId = (int)($stmtFindCust->fetchColumn() ?: 0);
            if ($userCustId > 0) {
                $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE (user_id = ? OR user_id IN (SELECT user_id FROM users WHERE customer_id = ?)) AND status = 'unread'");
                $stmt->execute([$userId, $userCustId]);
            } else {
                $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE user_id = ? AND status = 'unread'");
                $stmt->execute([$userId]);
            }
        } else {
            $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE (user_id = ? OR user_id IS NULL) AND status = 'unread'");
            $stmt->execute([$userId]);
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit();
        }
    } catch (Exception $e) {}
} elseif ($action === 'mark_read') {
    $notifId = intval($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
    if ($notifId > 0) {
        try {
            if ($userRole === 'admin') {
                $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE notification_id = ? AND (user_id = ? OR user_id IS NULL)");
                $stmt->execute([$notifId, $userId]);
            } elseif ($userRole === 'integrator') {
                $stmtFindCust = $conn->prepare("SELECT customer_id FROM users WHERE user_id = ?");
                $stmtFindCust->execute([$userId]);
                $userCustId = (int)($stmtFindCust->fetchColumn() ?: 0);
                if ($userCustId > 0) {
                    $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE notification_id = ? AND (user_id = ? OR user_id IN (SELECT user_id FROM users WHERE customer_id = ?))");
                    $stmt->execute([$notifId, $userId, $userCustId]);
                } else {
                    $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE notification_id = ? AND user_id = ?");
                    $stmt->execute([$notifId, $userId]);
                }
            } else {
                $stmt = $conn->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE notification_id = ? AND (user_id = ? OR user_id IS NULL)");
                $stmt->execute([$notifId, $userId]);
            }
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit();
            }
        } catch (Exception $e) {}
    }
}

header("Location: " . $redirectUrl);
exit();
