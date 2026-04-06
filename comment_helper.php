<?php
if (!defined('SNLDBCARPARTS_ACCESS')) die('Direct access not permitted.');

function comment_ensure_table($db): void {
    static $done = false;
    if ($done) return;
    $db->query(
        "CREATE TABLE IF NOT EXISTS CAR_COMMENTS (
            id          INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
            license     VARCHAR(12)      NOT NULL,
            author      VARCHAR(80)      NOT NULL DEFAULT '',
            comment     TEXT             NOT NULL,
            ip          VARCHAR(45)      NOT NULL DEFAULT '',
            created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved    TINYINT(1)       NOT NULL DEFAULT 1,
            INDEX (license),
            INDEX (ip),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $done = true;
}

/**
 * Fetch approved comments for a car, newest first.
 */
function comment_list($db, string $license): array {
    comment_ensure_table($db);
    $stmt = $db->prepare(
        "SELECT id, author, comment, created_at
         FROM CAR_COMMENTS WHERE license = ? AND approved = 1
         ORDER BY created_at DESC"
    );
    if (!$stmt) return [];
    $stmt->bind_param('s', $license);
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/**
 * Count recent submissions from an IP (rate limiting).
 */
function comment_ip_count($db, string $ip): int {
    comment_ensure_table($db);
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM CAR_COMMENTS WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    if (!$stmt) return 0;
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $cnt = 0;
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    return (int)$cnt;
}

/**
 * Add a comment. Returns true on success, string error message on failure.
 */
function comment_add($db, string $license, string $author, string $comment, string $ip): bool|string {
    comment_ensure_table($db);

    if (comment_ip_count($db, $ip) >= 3) {
        return 'Te veel reacties van dit IP-adres (max 3 per 24 uur).';
    }

    $stmt = $db->prepare(
        "INSERT INTO CAR_COMMENTS (license, author, comment, ip) VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) return 'Database fout bij opslaan.';
    $stmt->bind_param('ssss', $license, $author, $comment, $ip);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok ? true : 'Database fout bij opslaan.';
}

/**
 * Delete a comment by id (admin action).
 */
function comment_delete($db, int $id): void {
    comment_ensure_table($db);
    $stmt = $db->prepare("DELETE FROM CAR_COMMENTS WHERE id = ?");
    if (!$stmt) return;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Render comment text: converts YouTube/Vimeo URLs to embedded players,
 * then safely escapes and nl2br's the rest. Never trusts raw user input as HTML.
 */
function comment_render(string $text, bool $video_enabled = true): string {
    if (!$video_enabled) {
        return nl2br(htmlspecialchars($text));
    }

    $embeds = [];
    $idx    = 0;

    $play = '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);' .
            'width:56px;height:56px;background:rgba(0,0,0,0.72);border-radius:50%;' .
            'display:flex;align-items:center;justify-content:center;pointer-events:none;">' .
            '<span style="color:#fff;font-size:22px;margin-left:4px;">&#9654;</span></div>';

    // YouTube: youtube.com/watch?v=ID  or  youtu.be/ID
    $text = preg_replace_callback(
        '~https?://(?:www\.)?(?:youtube\.com/watch\?(?:[^\s&]*&)*v=|youtu\.be/)([A-Za-z0-9_-]{11})[^\s]*~',
        function ($m) use (&$embeds, &$idx, $play) {
            $id    = htmlspecialchars($m[1]);
            $src   = 'https://www.youtube-nocookie.com/embed/' . $id;
            $thumb = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
            $key   = "\x02" . $idx++ . "\x03";
            $embeds[$key] =
                '<div class="snl-vid" data-src="' . $src . '" title="Klik om video te laden" ' .
                'style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;' .
                'margin:8px 0;cursor:pointer;background:#000;">' .
                '<img src="' . $thumb . '" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;opacity:0.85;">' .
                $play . '</div>';
            return $key;
        },
        $text
    );

    // Vimeo: vimeo.com/NUMERIC_ID
    $text = preg_replace_callback(
        '~https?://(?:www\.)?vimeo\.com/(\d{5,12})[^\s]*~',
        function ($m) use (&$embeds, &$idx, $play) {
            $id  = (int) $m[1];
            $src = 'https://player.vimeo.com/video/' . $id;
            $key = "\x02" . $idx++ . "\x03";
            $embeds[$key] =
                '<div class="snl-vid" data-src="' . $src . '" title="Klik om video te laden" ' .
                'style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;' .
                'margin:8px 0;cursor:pointer;background:#1ab7ea;">' .
                '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-60%);' .
                'color:#fff;font-size:13px;font-weight:bold;text-align:center;pointer-events:none;">' .
                'Vimeo video</div>' . $play . '</div>';
            return $key;
        },
        $text
    );

    // Escape remaining text and restore embeds
    $out = nl2br(htmlspecialchars($text));
    foreach ($embeds as $key => $embed) {
        $out = str_replace($key, $embed, $out);
    }

    // Append click-to-load JS once per page (only when at least one embed was found)
    static $js_done = false;
    if ($idx > 0 && !$js_done) {
        $js_done = true;
        $out .= '<script>' .
            'document.addEventListener("click",function(e){' .
            'var el=e.target.closest(".snl-vid");if(!el)return;' .
            'var w=document.createElement("div");' .
            'w.style.cssText="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;margin:8px 0;";' .
            'var f=document.createElement("iframe");' .
            'f.src=el.dataset.src+"?autoplay=1";' .
            'f.style.cssText="position:absolute;top:0;left:0;width:100%;height:100%;border:0;";' .
            'f.setAttribute("allowfullscreen","");' .
            'f.setAttribute("allow","accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture");' .
            'w.appendChild(f);el.parentNode.replaceChild(w,el);' .
            '});' .
            '</script>';
    }

    return $out;
}

/**
 * Toggle approved flag (admin action).
 */
function comment_toggle($db, int $id): void {
    comment_ensure_table($db);
    $stmt = $db->prepare("UPDATE CAR_COMMENTS SET approved = 1 - approved WHERE id = ?");
    if (!$stmt) return;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}
