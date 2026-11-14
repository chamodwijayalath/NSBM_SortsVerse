<?php
/* Tiny shared layout for the new admin pages. */
function ui_head(string $title): void
{
    global $admin_page;
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
        . htmlspecialchars($title) . ' - SportsVerse Admin</title><style>
        body{font-family:Arial,sans-serif;background:#f2f4f8;margin:0;color:#1f2937}
        .wrap{max-width:1000px;margin:24px auto;padding:0 16px}
        h1,h2{color:#203864}.card{background:#fff;padding:20px;border-radius:8px;margin-bottom:22px;overflow-x:auto}
        table{border-collapse:collapse;width:100%}th,td{padding:9px 10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#203864;color:#fff}label{display:block;margin:10px 0 4px;font-size:14px}
        input,select{padding:8px;border:1px solid #cbd5e1;border-radius:6px;width:100%;box-sizing:border-box}
        button,.btn{background:#203864;color:#fff;border:0;border-radius:6px;padding:7px 12px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}
        button.ok{background:#0F8A5F}button.no{background:#b91c1c}button.mute{background:#64748b}
        .msg{background:#dcfce7;border:1px solid #86efac;padding:10px;border-radius:6px;margin-bottom:16px}
        .tag{padding:2px 8px;border-radius:999px;font-size:12px;background:#e5e7eb}
        .pending{background:#fef3c7}.approved,.Completed,.Open{background:#dcfce7}.rejected,.Cancelled{background:#fee2e2}.Live{background:#fecaca}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px}
        .stat{background:#fff;border-radius:8px;padding:18px}.stat b{display:block;font-size:30px;color:#0F8A5F}
        form.inline{display:inline}</style></head><body>';
    admin_nav($admin_page);
    echo '<div class="wrap">';
}
function ui_foot(): void { echo '</div></body></html>'; }
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
