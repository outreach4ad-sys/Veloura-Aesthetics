<?php
/**
 * Veloura Tec — Admin action: inquiry operations.
 */

require dirname(__DIR__) . '/_auth.php';

if (!is_post()) {
    redirect('admin/inquiries.php');
}

csrf_guard();

$op       = (string) input('op', '');
$id       = input_int('id');
$returnTo = admin_safe_return(input('return_to'), 'admin/inquiries.php');

if ($id <= 0 || inquiry_by_id($id) === null) {
    flash('error', 'That inquiry no longer exists.');
    redirect('admin/inquiries.php');
}

switch ($op) {
    case 'status':
        if (inquiry_set_status($id, (string) input('status', ''))) {
            flash('success', 'Status updated.');
        } else {
            flash('error', 'That status is not valid.');
        }
        break;

    case 'reply':
        $subject = trim((string) input('subject', ''));
        $body    = trim((string) ($_POST['body'] ?? ''));

        if ($subject === '' || $body === '') {
            flash('error', 'Please enter a subject and a message.');
            break;
        }

        $result = inquiry_reply_send($id, (int) (auth_user()['id'] ?? 0), $subject, $body);

        if ($result['ok']) {
            flash('success', 'Reply sent to the customer.');
        } elseif (mail_ready()) {
            flash('error', 'The reply could not be sent: ' . $result['error']);
        } else {
            flash('info', 'Reply saved, but email is not configured yet, so it was not delivered. Set it up in Settings → Email.');
        }
        break;

    case 'notes':
        inquiry_set_notes($id, (string) ($_POST['admin_notes'] ?? ''));
        flash('success', 'Notes saved.');
        break;

    case 'delete':
        inquiry_delete($id);
        flash('success', 'Inquiry deleted.');
        $returnTo = 'admin/inquiries.php';
        break;

    default:
        flash('error', 'Unknown action.');
}

redirect($returnTo);
