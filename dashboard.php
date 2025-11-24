<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $groupName = trim($_POST['group_name']);
    $members = $_POST['members'] ?? [];
    
    $members = array_filter($members, function($m) { return trim($m) !== ''; });

    if (empty($groupName)) {
        $error = "Group name is required";
    } elseif (count($members) < 2) {
        $error = "At least 2 members are required";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO `groups` (user_id, name) VALUES (?, ?)");
            $stmt->execute([$user_id, $groupName]);
            $group_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO members (group_id, name) VALUES (?, ?)");
            foreach ($members as $member) {
                $stmt->execute([$group_id, trim($member)]);
            }
            
            $pdo->commit();
            $success = "Group created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to create group: " . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("
    SELECT g.*, 
    (SELECT COUNT(*) FROM members m WHERE m.group_id = g.id) as member_count,
    (SELECT SUM(amount) FROM expenses e WHERE e.group_id = g.id) as total_spend
    FROM `groups` g 
    WHERE g.user_id = ? 
    ORDER BY g.created_at DESC
");
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SpendCalc</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="navbar glass">
        <div class="container nav-content">
            <h1 class="logo" style="font-size: 1.5rem;">SpendCalc</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid-groups">
            <div class="group-card glass" style="display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; min-height: 200px; border-style: dashed;" onclick="openModal('createGroupModal')">
                <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">+</div>
                <h3>Create New Group</h3>
            </div>

            <?php foreach ($groups as $group): ?>
            <a href="group.php?id=<?php echo $group['id']; ?>" class="group-card glass">
                <div class="group-header">
                    <div class="group-title"><?php echo htmlspecialchars($group['name']); ?></div>
                    <div class="group-meta"><?php echo date('M d', strtotime($group['created_at'])); ?></div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <div style="font-size: 0.875rem; color: var(--text-muted);">Total Spend</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--success);">₹<?php echo number_format($group['total_spend'] ?? 0, 2); ?></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="member-avatars">
                        <?php 
                        $mStmt = $pdo->prepare("SELECT name FROM members WHERE group_id = ? LIMIT 3");
                        $mStmt->execute([$group['id']]);
                        while($m = $mStmt->fetch()) {
                            echo '<div class="avatar" title="'.htmlspecialchars($m['name']).'">'.strtoupper(substr($m['name'], 0, 1)).'</div>';
                        }
                        if ($group['member_count'] > 3) {
                            echo '<div class="avatar">+'.($group['member_count'] - 3).'</div>';
                        }
                        ?>
                    </div>
                    <span style="font-size: 0.875rem; color: var(--text-muted);"><?php echo $group['member_count']; ?> members</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="createGroupModal" class="modal">
        <div class="modal-content glass auth-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Create Group</h2>
                <button onclick="closeModal('createGroupModal')" style="background: none; border: none; color: var(--text); cursor: pointer; font-size: 1.5rem;">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create_group">
                <div class="form-group">
                    <label class="form-label">Group Name</label>
                    <input type="text" name="group_name" class="form-control" required placeholder="Trip to Goa">
                </div>

                <div class="form-group">
                    <label class="form-label">Members</label>
                    <div id="membersList">
                        <input type="text" name="members[]" class="form-control" style="margin-bottom: 0.5rem;" placeholder="Member 1 Name" required>
                        <input type="text" name="members[]" class="form-control" style="margin-bottom: 0.5rem;" placeholder="Member 2 Name" required>
                        <input type="text" name="members[]" class="form-control" style="margin-bottom: 0.5rem;" placeholder="Member 3 Name">
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addMemberInput()" style="margin-top: 0.5rem; font-size: 0.875rem;">+ Add Member</button>
                </div>

                <button type="submit" class="btn btn-primary">Create Group</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function addMemberInput() {
            const container = document.getElementById('membersList');
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'members[]';
            input.className = 'form-control';
            input.style.marginBottom = '0.5rem';
            input.placeholder = 'Member Name';
            container.appendChild(input);
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
