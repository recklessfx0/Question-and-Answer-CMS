<?php declare(strict_types=1); ?>
<?php require BASE_PATH . '/views/admin/_nav.php'; ?>
<section class="page-head">
    <h1>Users</h1>
    <p class="meta">Manage roles for <?= count($users) ?> recent users.</p>
</section>
<section class="table-wrap">
    <table>
        <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Reputation</th><th>Update</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= h((string) $user['username']) ?></td>
                <td><?= h((string) $user['email']) ?></td>
                <td><?= h((string) $user['role']) ?></td>
                <td><?= (int) $user['reputation'] ?></td>
                <td>
                    <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/role" class="admin-table-form admin-role-form">
                        <?= $csrf->field('/admin/users/' . (int) $user['id'] . '/role') ?>
                        <select name="role">
                            <option value="user" <?= (string) $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <option value="moderator" <?= (string) $user['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                            <option value="administrator" <?= (string) $user['role'] === 'administrator' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                        <button type="submit">Set role</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
