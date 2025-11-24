<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$group_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM `groups` WHERE id = ? AND user_id = ?");
$stmt->execute([$group_id, $user_id]);
$group = $stmt->fetch();

if (!$group) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_expense') {
        $member_id = $_POST['member_id'];
        $amount = $_POST['amount'];
        $description = trim($_POST['description']);

        if (empty($amount) || $amount <= 0) {
            $error = "Invalid amount";
        } else {
            $stmt = $pdo->prepare("INSERT INTO expenses (group_id, member_id, amount, description) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$group_id, $member_id, $amount, $description])) {
                $success = "Expense added!";
            } else {
                $error = "Failed to add expense";
            }
        }
    } elseif ($action === 'edit_expense') {
        $expense_id = $_POST['expense_id'];
        $member_id = $_POST['member_id'];
        $amount = $_POST['amount'];
        $description = trim($_POST['description']);
        
        $check = $pdo->prepare("SELECT id FROM expenses WHERE id = ? AND group_id = ?");
        $check->execute([$expense_id, $group_id]);
        
        if ($check->fetch() && !empty($amount) && $amount > 0) {
            $stmt = $pdo->prepare("UPDATE expenses SET member_id = ?, amount = ?, description = ? WHERE id = ?");
            if ($stmt->execute([$member_id, $amount, $description, $expense_id])) {
                $success = "Expense updated!";
            } else {
                $error = "Failed to update expense";
            }
        } else {
            $error = "Invalid expense or amount";
        }
    } elseif ($action === 'delete_expense') {
        $expense_id = $_POST['expense_id'];
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND group_id = ?");
        if ($stmt->execute([$expense_id, $group_id])) {
            $success = "Expense deleted!";
        } else {
            $error = "Failed to delete expense";
        }
    }
}

$stmt = $pdo->prepare("
    SELECT m.*, COALESCE(SUM(e.amount), 0) as total_paid 
    FROM members m 
    LEFT JOIN expenses e ON m.id = e.member_id 
    WHERE m.group_id = ? 
    GROUP BY m.id
");
$stmt->execute([$group_id]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT e.*, m.name as member_name 
    FROM expenses e 
    JOIN members m ON e.member_id = m.id 
    WHERE e.group_id = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$group_id]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_group_spend = 0;
foreach ($members as $m) {
    $total_group_spend += $m['total_paid'];
}

$member_count = count($members);
$per_person_share = $member_count > 0 ? $total_group_spend / $member_count : 0;

$settlements = [];
if ($member_count > 0) {
    $debtors = [];
    $creditors = [];

    foreach ($members as $member) {
        $balance = $member['total_paid'] - $per_person_share;
        if ($balance < -0.01) {
            $debtors[] = ['name' => $member['name'], 'amount' => $balance];
        } elseif ($balance > 0.01) {
            $creditors[] = ['name' => $member['name'], 'amount' => $balance];
        }
    }

    usort($debtors, function($a, $b) { return $a['amount'] <=> $b['amount']; }); 
    usort($creditors, function($a, $b) { return $b['amount'] <=> $a['amount']; }); 

    $d = 0;
    $c = 0;

    while ($d < count($debtors) && $c < count($creditors)) {
        $debtor = &$debtors[$d];
        $creditor = &$creditors[$c];

        $amount = min(abs($debtor['amount']), $creditor['amount']);
        
        $settlements[] = [
            'from' => $debtor['name'],
            'to' => $creditor['name'],
            'amount' => $amount
        ];

        $debtor['amount'] += $amount;
        $creditor['amount'] -= $amount;

        if (abs($debtor['amount']) < 0.01) $d++;
        if ($creditor['amount'] < 0.01) $c++;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?> - SpendCalc</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar glass">
        <div class="container nav-content">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="dashboard.php" style="font-size: 1.5rem; color: var(--text-muted);">&larr;</a>
                <h1 class="logo" style="font-size: 1.5rem; margin: 0;"><?php echo htmlspecialchars($group['name']); ?></h1>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span>Total: <b style="color: var(--success);">₹<?php echo number_format($total_group_spend, 2); ?></b></span>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="split-layout">
            <div class="sidebar-card glass">
                <h3 style="margin-bottom: 1.5rem;">Balances</h3>
                <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <div style="display: flex; justify-content: space-between; color: var(--text-muted); font-size: 0.9rem;">
                        <span>Per person share</span>
                        <span>₹<?php echo number_format($per_person_share, 2); ?></span>
                    </div>
                </div>
                
                <div class="expense-list">
                    <?php foreach ($members as $member): 
                        $balance = $member['total_paid'] - $per_person_share;
                        $isOwed = $balance >= 0;
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div class="avatar"><?php echo strtoupper(substr($member['name'], 0, 1)); ?></div>
                            <div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($member['name']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Paid ₹<?php echo number_format($member['total_paid'], 2); ?></div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; color: <?php echo $isOwed ? 'var(--success)' : 'var(--danger)'; ?>">
                                <?php echo $isOwed ? '+' : ''; ?><?php echo number_format($balance, 2); ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                <?php echo $isOwed ? 'gets back' : 'owes'; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($settlements)): ?>
                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <h4 style="margin-bottom: 1rem; color: var(--text-muted);">Settlement Plan</h4>
                    <div class="expense-list">
                        <?php foreach ($settlements as $s): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--danger); font-weight: 600;"><?php echo htmlspecialchars($s['from']); ?></span>
                                <span style="color: var(--text-muted);">&rarr;</span>
                                <span style="color: var(--success); font-weight: 600;"><?php echo htmlspecialchars($s['to']); ?></span>
                            </div>
                            <div style="font-weight: 700;">₹<?php echo number_format($s['amount'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3>Recent Expenses</h3>
                </div>

                <?php if (empty($expenses)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted); border: 2px dashed rgba(255,255,255,0.1); border-radius: 1rem;">
                        No expenses yet. Click the + button to add one.
                    </div>
                <?php else: ?>
                    <div class="expense-list">
                        <?php foreach ($expenses as $expense): ?>
                        <div class="expense-item glass">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div class="avatar" style="background: var(--primary); color: white;">
                                    <?php echo strtoupper(substr($expense['member_name'], 0, 1)); ?>
                                </div>
                                <div class="expense-info">
                                    <h4><?php echo htmlspecialchars($expense['description']); ?></h4>
                                    <p><?php echo htmlspecialchars($expense['member_name']); ?> paid on <?php echo date('M d', strtotime($expense['created_at'])); ?></p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div class="expense-amount">
                                    ₹<?php echo number_format($expense['amount'], 2); ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button onclick="editExpense(<?php echo htmlspecialchars(json_encode($expense)); ?>)" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0.25rem;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </button>
                                    <button onclick="deleteExpense(<?php echo $expense['id']; ?>)" style="background: none; border: none; color: var(--danger); cursor: pointer; padding: 0.25rem;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button class="fab" onclick="resetModal(); openModal('expenseModal')">+</button>

    <div id="expenseModal" class="modal">
        <div class="modal-content glass auth-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 id="modalTitle">Add Expense</h2>
                <button onclick="closeModal('expenseModal')" style="background: none; border: none; color: var(--text); cursor: pointer; font-size: 1.5rem;">&times;</button>
            </div>
            
            <form method="POST" id="expenseForm">
                <input type="hidden" name="action" id="expenseAction" value="add_expense">
                <input type="hidden" name="expense_id" id="expenseId" value="">
                
                <div class="form-group">
                    <label class="form-label">Who paid?</label>
                    <select name="member_id" id="memberId" class="form-control" required>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" id="description" class="form-control" required placeholder="Dinner, Taxi, etc.">
                </div>

                <div class="form-group">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" name="amount" id="amount" class="form-control" required placeholder="0.00">
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary">Add Expense</button>
            </form>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_expense">
        <input type="hidden" name="expense_id" id="deleteExpenseId">
    </form>

    <script>
        function openModal(id) {
            if (id === 'expenseModal' && document.getElementById('expenseAction').value === 'add_expense') {
                resetModal();
            }
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            if (id === 'expenseModal') {
                setTimeout(resetModal, 300);
            }
        }

        function resetModal() {
            document.getElementById('modalTitle').innerText = 'Add Expense';
            document.getElementById('expenseAction').value = 'add_expense';
            document.getElementById('expenseId').value = '';
            document.getElementById('memberId').selectedIndex = 0;
            document.getElementById('amount').value = '';
            document.getElementById('description').value = '';
            document.getElementById('submitBtn').innerText = 'Add Expense';
        }

        function editExpense(expense) {
            document.getElementById('modalTitle').innerText = 'Edit Expense';
            document.getElementById('expenseAction').value = 'edit_expense';
            document.getElementById('expenseId').value = expense.id;
            document.getElementById('memberId').value = expense.member_id;
            document.getElementById('amount').value = expense.amount;
            document.getElementById('description').value = expense.description;
            document.getElementById('submitBtn').innerText = 'Update Expense';
            openModal('expenseModal');
        }

        function deleteExpense(id) {
            if (confirm('Are you sure you want to delete this expense?')) {
                document.getElementById('deleteExpenseId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                if (event.target.id === 'expenseModal') {
                    setTimeout(resetModal, 300);
                }
            }
        }
    </script>
</body>
</html>
