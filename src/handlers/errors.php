<?php
// Admin: app error log (v1.15)

function h_errors(): void {
    require_role('admin');
    view('errors', ['title' => 'Error log', 'rows' => all('SELECT * FROM app_errors ORDER BY last_at DESC LIMIT 200')]);
}

function h_errors_clear(): void {
    require_role('admin');
    $id = int_or_null(post('id'));
    $id ? q('DELETE FROM app_errors WHERE id = ?', [$id]) : q('DELETE FROM app_errors');
    flash($id ? 'Entry cleared.' : 'Error log cleared.');
    redirect('/errors');
}
