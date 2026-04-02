<?php
$pageTitle = 'Home Feed — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';

// Helper functions for this view
function getGreeting() {
    $hour = date('H');
    if ($hour < 12) return 'Good Morning';
    if ($hour < 18) return 'Good Afternoon';
    return 'Good Evening';
}

function getRelativeTime($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}
?>

<div class="page-header">
    <?php if(isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
        <div style="grid-column: 1 / -1; background: var(--red-bg); color: var(--red); padding: 12px 20px; border-radius: 8px; border: 1px solid var(--red-bdr); margin-bottom: 20px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            Invalid security token. The form has expired, please try again.
        </div>
    <?php endif; ?>
    <div>
        <h1 class="page-title"><?php echo getGreeting(); ?>, <?php echo explode(' ', $current_user_name)[0]; ?></h1>
        <p class="page-sub"><?php echo date('l, F j, Y'); ?></p>
    </div>
    <div class="header-actions">
        <button class="pill-btn" onclick="location.reload()"><i class="fa-solid fa-rotate"></i> Refresh</button>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 320px; align-items: start;">
    <!-- LEFT: FEED -->
    <div class="feed-column">
        <!-- CREATE POST -->
        <div class="card mb-20">
            <div class="card-body">
                <form action="<?php echo baseUrl('home/post'); ?>" method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="flex-row" style="align-items: flex-start;">
                        <div class="avatar" style="width: 36px; height: 36px;">
                            <?php echo strtoupper(substr($current_user_name, 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <textarea name="content" class="input-field" placeholder="What's on your mind?" style="min-height: 60px; border: none; background: var(--bg-subtle); padding: 12px;"></textarea>
                            
                            <div id="imagePreviewContainer" style="display: none; margin-top: 10px; position: relative;">
                                <img id="imagePreview" src="" style="max-height: 200px; border-radius: 8px; border: 1px solid var(--border);">
                                <button type="button" onclick="clearImage()" style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer;">&times;</button>
                            </div>

                            <div class="flex-between mt-10">
                                <div class="flex-row">
                                    <label class="icon-btn" title="Upload Photo" style="width: 30px; height: 30px;">
                                        <i class="fa-solid fa-image" style="font-size: 12px;"></i>
                                        <input type="file" name="post_image" id="postImageInput" accept="image/*" style="display: none;" onchange="previewImage(this)">
                                    </label>
                                    <?php if($is_hr || $is_ceo): ?>
                                    <label class="flex-row" style="font-size: 11px; color: var(--ink-3); cursor: pointer;">
                                        <input type="checkbox" name="is_announcement" value="1"> Announcement
                                    </label>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="btn-primary">Post</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ACTIVITY FEED -->
        <div class="section-hd">
            <span class="section-hd-label">Activity Feed</span>
            <div class="section-hd-line"></div>
        </div>

        <?php if(empty($posts)): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; color: var(--ink-4); padding: 40px;">
                    <i class="fa-solid fa-rss" style="font-size: 24px; margin-bottom: 10px; opacity: 0.3;"></i>
                    <p>No activity yet. Start the conversation!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
            <div class="card mb-10">
                <div class="card-body">
                    <div class="flex-between mb-10">
                        <div class="flex-row">
                            <div class="avatar" style="width: 32px; height: 32px;">
                                <?php echo strtoupper(substr($post['first_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></div>
                                <div style="font-size: 10px; color: var(--ink-4);"><?php echo htmlspecialchars($post['job_title'] ?? 'Employee'); ?> • <?php echo getRelativeTime(strtotime($post['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php if($post['is_announcement'] == 1): ?>
                            <span class="tag tag-teal">Announcement</span>
                        <?php elseif(isset($post['is_birthday']) && $post['is_birthday'] == 1): ?>
                            <span class="tag tag-amber">Birthday</span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size: 13px; color: var(--ink-2); line-height: 1.5; margin-bottom: 12px; white-space: pre-wrap;"><?php echo htmlspecialchars($post['content']); ?></div>

                    <?php if(!empty($post['image_path']) && file_exists(BASE_PATH . '/public/' . $post['image_path'])): ?>
                        <div style="border-radius: 8px; overflow: hidden; border: 1px solid var(--border-lt); margin-bottom: 12px;">
                            <img src="<?php echo baseUrl($post['image_path']); ?>" style="width: 100%; height: auto; display: block;">
                        </div>
                    <?php endif; ?>

                    <div class="flex-row" style="border-top: 1px solid var(--border-lt); padding-top: 10px; gap: 15px;">
                        <button class="btn-ghost" style="font-size: 11px; color: var(--ink-3); padding: 4px 8px; border-radius: 4px;"><i class="fa-regular fa-thumbs-up" style="margin-right: 5px;"></i> Like</button>
                        <button class="btn-ghost" style="font-size: 11px; color: var(--ink-3); padding: 4px 8px; border-radius: 4px;"><i class="fa-regular fa-comment" style="margin-right: 5px;"></i> Comment</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- RIGHT: SIDEBAR WIDGETS -->
    <div class="widgets-column">
        <!-- CALENDAR -->
        <div class="card mb-10">
            <div class="card-head">
                <div class="card-title"><i class="fa-regular fa-calendar"></i> <?php echo $monthName . ' ' . $calYear; ?></div>
                <div class="flex-row" style="gap: 4px;">
                    <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="icon-btn" style="width: 24px; height: 24px; font-size: 10px;"><i class="fa-solid fa-chevron-left"></i></a>
                    <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="icon-btn" style="width: 24px; height: 24px; font-size: 10px;"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="card-body" style="padding: 10px;">
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; text-align: center; margin-bottom: 5px;">
                    <?php foreach (['S','M','T','W','T','F','S'] as $day): ?>
                        <div style="font-size: 9px; font-weight: 700; color: var(--ink-4);"><?php echo $day; ?></div>
                    <?php endforeach; ?>
                </div>
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
                    <?php 
                    for ($i = 0; $i < $dayOfWeek; $i++) echo '<div></div>';
                    
                    $todayDay = (int)date('j');
                    $todayMonth = (int)date('m');
                    $todayYear = (int)date('Y');

                    for ($day = 1; $day <= $numberDays; $day++):
                        $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
                        $isToday = ($day === $todayDay && $calMonth === $todayMonth && $calYear === $todayYear);
                        $hasHoliday = isset($finalHolidays[$dateStr]);
                        $holidayInfo = $hasHoliday ? $finalHolidays[$dateStr] : null;
                        
                        $bg = '';
                        $color = 'var(--ink-2)';
                        $weight = '400';
                        $border = 'none';

                        if ($isToday) {
                            $bg = 'var(--teal)';
                            $color = '#fff';
                            $weight = '700';
                        } elseif ($hasHoliday) {
                            if ($holidayInfo['type'] === 'REGULAR') { $bg = 'var(--red-bg)'; $color = 'var(--red)'; }
                            else { $bg = 'var(--amber-bg)'; $color = 'var(--amber)'; }
                            $weight = '600';
                        }
                    ?>
                        <div style="height: 32px; display: flex; align-items: center; justify-content: center; font-size: 11px; border-radius: 6px; cursor: pointer; transition: background 0.2s; 
                            background: <?php echo $bg; ?>; color: <?php echo $color; ?>; font-weight: <?php echo $weight; ?>;"
                            title="<?php echo $hasHoliday ? htmlspecialchars($holidayInfo['name']) : ''; ?>"
                            <?php if ($is_hr): ?>onclick="openHolidayModal('<?php echo $dateStr; ?>', <?php echo $hasHoliday ? 'true' : 'false'; ?>, '<?php echo addslashes($holidayInfo['name'] ?? ''); ?>', '<?php echo $holidayInfo['type'] ?? ''; ?>')"<?php endif; ?>>
                            <?php echo $day; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- BIRTHDAYS -->
        <div class="card mb-10">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-cake-candles"></i> Birthdays</div></div>
            <div class="card-body" style="padding: 12px;">
                <?php if (empty($upcoming_birthdays)): ?>
                    <p style="font-size: 11px; color: var(--ink-4); text-align: center;">No birthdays this month.</p>
                <?php else: ?>
                    <?php foreach (array_slice($upcoming_birthdays, 0, 5) as $bday): ?>
                    <div class="flex-row mb-10" style="gap: 8px;">
                        <div class="avatar" style="width: 24px; height: 24px; font-size: 8px;">
                            <?php echo strtoupper(substr($bday['first_name'], 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 12px; font-weight: 500; color: var(--ink-2);"><?php echo htmlspecialchars($bday['first_name']); ?></div>
                            <div style="font-size: 10px; color: var(--ink-4);"><?php echo date('M d', strtotime($bday['date_of_birth'])); ?></div>
                        </div>
                        <?php if ((int)$bday['bday_day'] === (int)date('j') && $calMonth === (int)date('m')): ?>
                            <span style="font-size: 12px;">🎉</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- HOLIDAYS -->
        <div class="card">
            <div class="card-head"><div class="card-title"><i class="fa-solid fa-flag"></i> Holidays</div></div>
            <div class="card-body" style="padding: 12px;">
                <?php 
                $monthHolidays = array_filter($finalHolidays, function($date) use ($calYear, $calMonth) {
                    return strpos($date, sprintf('%04d-%02d', $calYear, $calMonth)) === 0;
                }, ARRAY_FILTER_USE_KEY);
                ?>
                <?php if (empty($monthHolidays)): ?>
                    <p style="font-size: 11px; color: var(--ink-4); text-align: center;">No holidays this month.</p>
                <?php else: ?>
                    <?php foreach ($monthHolidays as $hDate => $hInfo): ?>
                    <div class="flex-between mb-10">
                        <div>
                            <div style="font-size: 11px; font-weight: 500;"><?php echo htmlspecialchars($hInfo['name']); ?></div>
                            <div style="font-size: 10px; color: var(--ink-4);"><?php echo date('M d', strtotime($hDate)); ?></div>
                        </div>
                        <span class="tag <?php echo ($hInfo['type'] === 'REGULAR') ? 'tag-red' : 'tag-amber'; ?>" style="font-size: 8px;"><?php echo $hInfo['type']; ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreviewContainer').style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    function clearImage() {
        document.getElementById('postImageInput').value = '';
        document.getElementById('imagePreview').src = '';
        document.getElementById('imagePreviewContainer').style.display = 'none';
    }
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
