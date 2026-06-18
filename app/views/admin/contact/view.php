<?php
require_once __DIR__ . '/../../../core/Auth.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../models/ContactSubmission.php';
require_once __DIR__ . '/../../../core/EmailService.php';

$auth = new Auth($db);
$auth->requireAdmin();

$contactModel = new ContactSubmission($db);
$currentUser = $auth->user();

// Get submission ID
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: /app/views/admin/contact/list.php');
    exit;
}

// Get submission details
$submission = $contactModel->getById($id);

if (!$submission) {
    header('Location: /app/views/admin/contact/list.php?error=not_found');
    exit;
}

// Get all replies
$replies = $contactModel->getReplies($id);

// Handle form submissions
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['pending', 'in_progress', 'resolved', 'closed'])) {
            $contactModel->updateStatus($id, $newStatus, $currentUser['id']);
            $successMessage = 'Status updated successfully!';
            $submission['status'] = $newStatus;
        }
    }
    
    if ($action === 'update_notes') {
        $notes = $_POST['admin_notes'] ?? '';
        $contactModel->updateNotes($id, $notes);
        $successMessage = 'Notes updated successfully!';
        $submission['admin_notes'] = $notes;
    }
    
    if ($action === 'send_reply') {
        $replyMessage = $_POST['reply_message'] ?? '';
        $sendEmail = isset($_POST['send_email']);
        
        if (!empty($replyMessage)) {
            try {
                // Add reply to database
                $contactModel->addReply($id, $currentUser['id'], $replyMessage, $sendEmail);
                
                // Send email if checkbox is checked
                if ($sendEmail) {
                    $emailService = new EmailService();
                    
                    $emailSubject = "Re: {$submission['subject']} [Ref: IA-" . str_pad($id, 6, '0', STR_PAD_LEFT) . "]";
                    
                    $emailBody = "
                        <h2>Hello {$submission['name']},</h2>
                        <p>Thank you for contacting Internship Adda. Here's our response to your message:</p>
                        
                        <div style='background: #f5f5f5; padding: 20px; border-left: 4px solid #16a34a; margin: 20px 0;'>
                            <p style='margin: 0; white-space: pre-line;'>{$replyMessage}</p>
                        </div>
                        
                        <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                        
                        <p><strong>Your Original Message:</strong></p>
                        <div style='background: #fafafa; padding: 15px; border-radius: 5px;'>
                            <p style='margin: 0;'><strong>Subject:</strong> {$submission['subject']}</p>
                            <p style='margin: 10px 0 0 0;'><strong>Message:</strong></p>
                            <p style='margin: 5px 0 0 0; white-space: pre-line;'>{$submission['message']}</p>
                        </div>
                        
                        <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                        
                        <p>If you have any further questions, feel free to reply to this email.</p>
                        
                        <p><strong>Reference ID:</strong> IA-" . str_pad($id, 6, '0', STR_PAD_LEFT) . "</p>
                        
                        <br>
                        <p>Best Regards,<br>
                        <strong>{$currentUser['name']}</strong><br>
                        Internship Adda Team</p>
                        
                        <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                        
                        <p style='font-size: 12px; color: #666;'>
                            📧 Email: support@internshipadda.com<br>
                            📱 Phone: +91 72097 47479<br>
                            💬 WhatsApp: +91 72097 47479
                        </p>
                    ";
                    
                    $emailService->send($submission['email'], $emailSubject, $emailBody);
                    $successMessage = 'Reply sent successfully via email!';
                } else {
                    $successMessage = 'Reply saved successfully!';
                }
                
                // Refresh replies
                $replies = $contactModel->getReplies($id);
                
                // Update status to in_progress if it was pending
                if ($submission['status'] === 'pending') {
                    $contactModel->updateStatus($id, 'in_progress', $currentUser['id']);
                    $submission['status'] = 'in_progress';
                }
                
            } catch (Exception $e) {
                $errorMessage = 'Failed to send reply: ' . $e->getMessage();
            }
        } else {
            $errorMessage = 'Please enter a reply message';
        }
    }
    
    if ($action === 'delete') {
        $contactModel->delete($id);
        header('Location: /app/views/admin/contact/list.php?success=deleted');
        exit;
    }
}

// Status color mapping
$statusColors = [
    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
    'in_progress' => 'bg-blue-100 text-blue-700 border-blue-300',
    'resolved' => 'bg-green-100 text-green-700 border-green-300',
    'closed' => 'bg-gray-100 text-gray-700 border-gray-300'
];

// Priority color mapping
$priorityColors = [
    'low' => 'bg-gray-100 text-gray-700',
    'medium' => 'bg-blue-100 text-blue-700',
    'high' => 'bg-orange-100 text-orange-700',
    'urgent' => 'bg-red-100 text-red-700'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Submission #<?= $id ?> - Admin Panel</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            500: '#16a34a',
                            600: '#15803d',
                            700: '#14532d'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>
    
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <a href="/app/views/admin/contact/list.php" class="text-green-600 hover:text-green-700 font-medium mb-2 inline-block">
                        ← Back to All Submissions
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900">Contact Submission #<?= $id ?></h1>
                    <p class="text-gray-600 mt-2">
                        Reference: <span class="font-mono font-semibold">IA-<?= str_pad($id, 6, '0', STR_PAD_LEFT) ?></span>
                    </p>
                </div>
                <div class="flex gap-3">
                    <span class="px-4 py-2 rounded-xl font-semibold text-sm <?= $priorityColors[$submission['priority']] ?>">
                        <?= ucfirst($submission['priority']) ?> Priority
                    </span>
                    <span class="px-4 py-2 rounded-xl font-semibold text-sm border <?= $statusColors[$submission['status']] ?>">
                        <?= ucfirst(str_replace('_', ' ', $submission['status'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($successMessage): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl">
                ✅ <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl">
                ❌ <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column: Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Submission Details -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">📧 Message Details</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-semibold text-gray-600">Subject</label>
                            <p class="text-lg text-gray-900 mt-1"><?= htmlspecialchars($submission['subject']) ?></p>
                        </div>
                        
                        <div>
                            <label class="text-sm font-semibold text-gray-600">Message</label>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <p class="text-gray-900 whitespace-pre-line"><?= htmlspecialchars($submission['message']) ?></p>
                            </div>
                        </div>
                        
                        <div class="grid md:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Submitted</label>
                                <p class="text-gray-900 mt-1">
                                    <?= date('F j, Y', strtotime($submission['created_at'])) ?>
                                    <span class="text-gray-500">at <?= date('g:i A', strtotime($submission['created_at'])) ?></span>
                                </p>
                            </div>
                            
                            <?php if ($submission['resolved_at']): ?>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Resolved</label>
                                <p class="text-gray-900 mt-1">
                                    <?= date('F j, Y', strtotime($submission['resolved_at'])) ?>
                                    <span class="text-gray-500">at <?= date('g:i A', strtotime($submission['resolved_at'])) ?></span>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Reply Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">💬 Send Reply</h2>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="send_reply">
                        
                        <div>
                            <label for="reply_message" class="block text-sm font-semibold text-gray-700 mb-2">
                                Reply Message *
                            </label>
                            <textarea 
                                id="reply_message"
                                name="reply_message" 
                                rows="6" 
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                placeholder="Type your reply here..."
                            ></textarea>
                        </div>
                        
                        <div class="flex items-center gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <input 
                                type="checkbox" 
                                id="send_email" 
                                name="send_email"
                                checked
                                class="w-5 h-5 text-green-600 rounded focus:ring-2 focus:ring-green-500"
                            >
                            <label for="send_email" class="text-sm font-medium text-gray-700">
                                📧 Send reply via email to <span class="font-semibold"><?= htmlspecialchars($submission['email']) ?></span>
                            </label>
                        </div>
                        
                        <div class="flex gap-3">
                            <button 
                                type="submit"
                                class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors"
                            >
                                Send Reply
                            </button>
                            <button 
                                type="button"
                                onclick="document.getElementById('reply_message').value = ''"
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition-colors"
                            >
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Reply History -->
                <?php if (!empty($replies)): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">📜 Reply History</h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($replies as $reply): ?>
                        <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                        <?= strtoupper(substr($reply['admin_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($reply['admin_name']) ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?= date('M j, Y \a\t g:i A', strtotime($reply['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <?php if ($reply['sent_via_email']): ?>
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-semibold">
                                        📧 Sent via Email
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-700 whitespace-pre-line mt-3"><?= htmlspecialchars($reply['reply_message']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Sidebar -->
            <div class="space-y-6">
                <!-- Sender Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">👤 Sender Information</h2>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase">Name</label>
                            <p class="text-gray-900 mt-1"><?= htmlspecialchars($submission['name']) ?></p>
                        </div>
                        
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase">Email</label>
                            <a href="mailto:<?= htmlspecialchars($submission['email']) ?>" 
                               class="text-green-600 hover:text-green-700 font-medium mt-1 block break-all">
                                <?= htmlspecialchars($submission['email']) ?>
                            </a>
                        </div>
                        
                        <?php if ($submission['phone']): ?>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase">Phone</label>
                            <a href="tel:<?= htmlspecialchars($submission['phone']) ?>" 
                               class="text-green-600 hover:text-green-700 font-medium mt-1 block">
                                <?= htmlspecialchars($submission['phone']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="pt-3 border-t border-gray-200">
                            <label class="text-xs font-semibold text-gray-600 uppercase">IP Address</label>
                            <p class="text-gray-700 mt-1 font-mono text-sm"><?= htmlspecialchars($submission['ip_address']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Status Management -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">🔄 Update Status</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_status">
                        
                        <select 
                            name="status" 
                            onchange="this.form.submit()"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        >
                            <option value="pending" <?= $submission['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="in_progress" <?= $submission['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="resolved" <?= $submission['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="closed" <?= $submission['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </form>
                </div>

                <!-- Admin Notes -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">📝 Admin Notes</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notes">
                        
                        <textarea 
                            name="admin_notes" 
                            rows="4"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 mb-3"
                            placeholder="Add internal notes (not visible to user)..."
                        ><?= htmlspecialchars($submission['admin_notes'] ?? '') ?></textarea>
                        
                        <button 
                            type="submit"
                            class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-gray-700 transition-colors"
                        >
                            Save Notes
                        </button>
                    </form>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">⚡ Quick Actions</h2>
                    
                    <div class="space-y-2">
                        <a href="mailto:<?= htmlspecialchars($submission['email']) ?>?subject=Re: <?= urlencode($submission['subject']) ?>" 
                           class="block w-full text-center bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                            📧 Open in Email Client
                        </a>
                        
                        <?php if ($submission['phone']): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $submission['phone']) ?>" 
                           target="_blank"
                           class="block w-full text-center bg-green-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                            💬 WhatsApp
                        </a>
                        <?php endif; ?>
                        
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this submission?');">
                            <input type="hidden" name="action" value="delete">
                            <button 
                                type="submit"
                                class="w-full bg-red-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors"
                            >
                                🗑️ Delete Submission
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
