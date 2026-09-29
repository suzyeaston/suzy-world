<?php
declare(strict_types=1);

namespace SuzyWorld;

final class Admin {
    public static function bootstrap(): void {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_suzy_world_import', [self::class, 'handle_import']);
        add_action('admin_post_suzy_world_save_album', [self::class, 'handle_save_album']);
    }

    public static function menu(): void {
        add_menu_page(
            'SUZY//WORLD',
            'SUZY//WORLD',
            'manage_options',
            'suzy-world',
            [self::class, 'render'],
            'dashicons-album',
            58
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $counts = Repository::counts();
        $message = isset($_GET['sw_message'])
            ? sanitize_text_field(wp_unslash((string) $_GET['sw_message']))
            : '';

        $messageType = isset($_GET['sw_type'])
            ? sanitize_key((string) $_GET['sw_type'])
            : 'success';

        $api = esc_url(rest_url('suzy-world/v1'));
        $albums = Repository::entities([
            'domain' => 'music',
            'kind' => 'album',
            'limit' => 20,
        ]);

        ?>
        <div class="wrap sw-admin">
            <style>
                .sw-admin{max-width:1180px}
                .sw-admin .sw-hero{
                    margin:18px 0 24px;
                    padding:26px 28px;
                    color:#f7f4ed;
                    background:#0a0b0f;
                    border-left:5px solid #b8ff39;
                }
                .sw-admin .sw-hero h1{
                    color:#f7f4ed;
                    font-size:42px;
                    letter-spacing:-.04em;
                    margin:0 0 6px;
                }
                .sw-admin .sw-hero p{font-size:16px;margin:0;color:#c4c7cf}
                .sw-admin .sw-grid{
                    display:grid;
                    grid-template-columns:minmax(0,2fr) minmax(280px,1fr);
                    gap:20px;
                    align-items:start;
                }
                .sw-admin .sw-card{
                    background:#fff;
                    border:1px solid #dcdcde;
                    padding:22px;
                    box-shadow:0 1px 1px rgba(0,0,0,.03);
                }
                .sw-admin .sw-card h2{margin-top:0}
                .sw-admin .sw-two{
                    display:grid;
                    grid-template-columns:1fr 1fr;
                    gap:14px;
                }
                .sw-admin label.sw-label{
                    display:block;
                    margin:14px 0 5px;
                    font-weight:600;
                }
                .sw-admin input[type=text],
                .sw-admin input[type=number],
                .sw-admin input[type=url],
                .sw-admin textarea,
                .sw-admin select{
                    width:100%;
                    max-width:none;
                }
                .sw-admin textarea{min-height:105px}
                .sw-admin .sw-note{
                    color:#646970;
                    font-size:12px;
                    margin-top:5px;
                }
                .sw-admin .sw-actions{
                    display:flex;
                    gap:10px;
                    align-items:center;
                    flex-wrap:wrap;
                    margin-top:22px;
                }
                .sw-admin .sw-publish.button-primary{
                    background:#111318;
                    border-color:#111318;
                }
                .sw-admin .sw-publish.button-primary:hover{
                    background:#2a2e36;
                    border-color:#2a2e36;
                }
                .sw-admin .sw-stats{
                    display:grid;
                    grid-template-columns:repeat(2,1fr);
                    gap:8px;
                    margin-bottom:18px;
                }
                .sw-admin .sw-stat{
                    padding:14px;
                    background:#f6f7f7;
                    border:1px solid #dcdcde;
                }
                .sw-admin .sw-stat strong{display:block;font-size:24px}
                .sw-admin .sw-recent{
                    margin:0;
                    padding:0;
                    list-style:none;
                }
                .sw-admin .sw-recent li{
                    border-top:1px solid #eee;
                    padding:10px 0;
                }
                .sw-admin .sw-status{
                    display:inline-block;
                    margin-left:6px;
                    padding:2px 6px;
                    font-size:10px;
                    text-transform:uppercase;
                    border:1px solid #c3c4c7;
                    border-radius:10px;
                    color:#646970;
                }
                .sw-admin details{
                    margin-top:20px;
                    background:#fff;
                    border:1px solid #dcdcde;
                    padding:16px 20px;
                }
                .sw-admin summary{
                    cursor:pointer;
                    font-weight:600;
                }
                @media(max-width:900px){
                    .sw-admin .sw-grid,.sw-admin .sw-two{grid-template-columns:1fr}
                }
            </style>

            <div class="sw-hero">
                <h1>SUZY//WORLD</h1>
                <p>Teach the public world one thing at a time. Start with records. Let the graph get strange later.</p>
            </div>

            <?php if ($message !== '') : ?>
                <div class="notice <?php echo $messageType === 'error' ? 'notice-error' : 'notice-success'; ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="sw-grid">
                <section class="sw-card">
                    <h2>Add an album</h2>
                    <p>This is the normal way to add a record. Save it privately as a draft first, or publish it immediately to the public API.</p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="suzy_world_save_album">
                        <?php wp_nonce_field('suzy_world_save_album'); ?>

                        <div class="sw-two">
                            <div>
                                <label class="sw-label" for="sw_artist">Artist *</label>
                                <input type="text" id="sw_artist" name="artist" required placeholder="Björk">
                            </div>

                            <div>
                                <label class="sw-label" for="sw_album">Album *</label>
                                <input type="text" id="sw_album" name="album" required placeholder="Homogenic">
                            </div>
                        </div>

                        <div class="sw-two">
                            <div>
                                <label class="sw-label" for="sw_year">Year</label>
                                <input type="number" id="sw_year" name="year" min="1900" max="2100" step="1">
                            </div>

                            <div>
                                <label class="sw-label" for="sw_rating">Rating, if you want one</label>
                                <input type="number" id="sw_rating" name="rating" min="0" max="10" step="0.1" placeholder="9.5">
                                <p class="sw-note">Optional. The review matters more than the number.</p>
                            </div>
                        </div>

                        <label class="sw-label" for="sw_review">Your review</label>
                        <textarea id="sw_review" name="review" placeholder="What do you actually think about this record?"></textarea>

                        <label class="sw-label" for="sw_sonic_notes">Musical / production notes</label>
                        <textarea id="sw_sonic_notes" name="sonic_notes" placeholder="Harmony, bass movement, texture, recording choices, arrangement, space, weird little noises…"></textarea>

                        <div class="sw-two">
                            <div>
                                <label class="sw-label" for="sw_tracks">Favourite tracks</label>
                                <input type="text" id="sw_tracks" name="favorite_tracks" placeholder="Jóga; Unravel">
                                <p class="sw-note">Separate multiple tracks with semicolons.</p>
                            </div>

                            <div>
                                <label class="sw-label" for="sw_associations">Associations / influences</label>
                                <input type="text" id="sw_associations" name="associations" placeholder="a film; another artist; a memory">
                                <p class="sw-note">These can become graph relationships later.</p>
                            </div>
                        </div>

                        <label class="sw-label" for="sw_lesson">What did this record teach you?</label>
                        <textarea id="sw_lesson" name="lesson" placeholder="How did it change the way you listen, play, write, record, or think?"></textarea>

                        <label class="sw-label" for="sw_tags">Tags</label>
                        <input type="text" id="sw_tags" name="tags" placeholder="genre:art-pop; production:negative space; personal:changed my playing">
                        <p class="sw-note">
                            Use semicolons. You can optionally categorize tags with <code>category:tag</code>.
                        </p>

                        <hr style="margin:28px 0">

                        <h3>Album cover, optional</h3>
                        <p class="sw-note">You can leave this entire section blank. Adding an image URL does not automatically display it.</p>

                        <label class="sw-label" for="sw_cover_url">Cover image URL</label>
                        <input type="url" id="sw_cover_url" name="cover_url" placeholder="https://…">

                        <div class="sw-two">
                            <div>
                                <label class="sw-label" for="sw_cover_credit">Credit</label>
                                <input type="text" id="sw_cover_credit" name="cover_credit" placeholder="Artwork / photography credit">
                            </div>

                            <div>
                                <label class="sw-label" for="sw_cover_creator">Creator</label>
                                <input type="text" id="sw_cover_creator" name="cover_creator">
                            </div>
                        </div>

                        <label class="sw-label" for="sw_cover_source_url">Source page</label>
                        <input type="url" id="sw_cover_source_url" name="cover_source_url" placeholder="Official label / artist / licensed source page">

                        <div class="sw-two">
                            <div>
                                <label class="sw-label" for="sw_cover_rights">Rights basis</label>
                                <select id="sw_cover_rights" name="cover_rights">
                                    <option value="unknown">Unknown / not reviewed yet</option>
                                    <option value="permission">Permission / licensed</option>
                                    <option value="fair-dealing-review">Fair dealing: criticism/review</option>
                                    <option value="public-domain">Public domain</option>
                                    <option value="official-embed">Official / platform-provided embed</option>
                                </select>
                            </div>

                            <div>
                                <label class="sw-label" for="sw_cover_rights_note">Rights note</label>
                                <input type="text" id="sw_cover_rights_note" name="cover_rights_note" placeholder="Why is this okay to display?">
                            </div>
                        </div>

                        <p style="margin-top:14px">
                            <label>
                                <input type="checkbox" name="cover_display" value="1">
                                Display this cover on the public album page.
                            </label>
                        </p>

                        <div class="sw-actions">
                            <button class="button button-secondary button-large" type="submit" name="save_mode" value="draft">
                                Save Draft
                            </button>
                            <button class="button button-primary button-large sw-publish" type="submit" name="save_mode" value="publish">
                                Publish to SUZY//WORLD
                            </button>
                        </div>
                    </form>
                </section>

                <aside>
                    <div class="sw-card">
                        <h2>The world so far</h2>

                        <div class="sw-stats">
                            <?php foreach ($counts as $label => $count) : ?>
                                <div class="sw-stat">
                                    <strong><?php echo esc_html((string) $count); ?></strong>
                                    <span><?php echo esc_html($label); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <h3>Recent albums</h3>

                        <?php if (!$albums) : ?>
                            <p>Nothing yet. The clean slate is staring at you.</p>
                        <?php else : ?>
                            <ul class="sw-recent">
                                <?php foreach ($albums as $album) : ?>
                                    <?php
                                    $metadata = is_array($album['metadata'] ?? null) ? $album['metadata'] : [];
                                    $artist = (string) ($metadata['artist'] ?? '');
                                    ?>
                                    <li>
                                        <strong><?php echo esc_html((string) $album['name']); ?></strong>
                                        <?php if ($artist !== '') : ?>
                                            <br><span><?php echo esc_html($artist); ?></span>
                                        <?php endif; ?>
                                        <span class="sw-status"><?php echo esc_html((string) $album['status']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <hr>

                        <h3>Public API</h3>
                        <p><a href="<?php echo esc_url($api . '/albums'); ?>" target="_blank" rel="noopener">View albums JSON ↗</a></p>
                        <p><a href="<?php echo esc_url($api . '/tags'); ?>" target="_blank" rel="noopener">View tags JSON ↗</a></p>
                    </div>
                </aside>
            </div>

            <details>
                <summary>Bulk import CSV / JSON</summary>

                <p>For later, when you want to import a whole pile of records at once. You do not need this for normal album reviewing.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="suzy_world_import">
                    <?php wp_nonce_field('suzy_world_import'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="sw_import_file">CSV / JSON</label></th>
                            <td><input type="file" id="sw_import_file" name="sw_import_file" accept=".csv,.json" required></td>
                        </tr>

                        <tr>
                            <th scope="row">Publish immediately</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="publish" value="1">
                                    Make imported records public immediately.
                                </label>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Import album batch'); ?>
                </form>
            </details>

            <details>
                <summary>Developer / API details</summary>

                <p>Public reads:</p>
                <ul>
                    <li><a href="<?php echo esc_url($api . '/schema'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/schema'); ?></a></li>
                    <li><a href="<?php echo esc_url($api . '/albums'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/albums'); ?></a></li>
                    <li><a href="<?php echo esc_url($api . '/entities'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/entities'); ?></a></li>
                    <li><a href="<?php echo esc_url($api . '/tags'); ?>" target="_blank" rel="noopener"><?php echo esc_html($api . '/tags'); ?></a></li>
                </ul>

                <p>External writes use WordPress authentication / Application Passwords.</p>
                <p>Public album shortcode: <code>[suzy_world_albums]</code></p>
            </details>
        </div>
        <?php
    }

    public static function handle_save_album(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }

        check_admin_referer('suzy_world_save_album');

        $artist = sanitize_text_field(wp_unslash((string) ($_POST['artist'] ?? '')));
        $album = sanitize_text_field(wp_unslash((string) ($_POST['album'] ?? '')));

        if ($artist === '' || $album === '') {
            self::redirect('Artist and album are required.', 'error');
        }

        $saveMode = sanitize_key((string) ($_POST['save_mode'] ?? 'draft'));
        $publish = $saveMode === 'publish';

        $row = [
            'artist' => $artist,
            'album' => $album,
            'year' => absint($_POST['year'] ?? 0) ?: '',
            'review' => wp_kses_post(wp_unslash((string) ($_POST['review'] ?? ''))),
            'rating' => sanitize_text_field(wp_unslash((string) ($_POST['rating'] ?? ''))),
            'tags' => sanitize_text_field(wp_unslash((string) ($_POST['tags'] ?? ''))),
            'sonic_notes' => sanitize_textarea_field(wp_unslash((string) ($_POST['sonic_notes'] ?? ''))),
            'favorite_tracks' => sanitize_text_field(wp_unslash((string) ($_POST['favorite_tracks'] ?? ''))),
            'associations' => sanitize_text_field(wp_unslash((string) ($_POST['associations'] ?? ''))),
            'lesson' => sanitize_textarea_field(wp_unslash((string) ($_POST['lesson'] ?? ''))),
            'cover_url' => esc_url_raw(wp_unslash((string) ($_POST['cover_url'] ?? ''))),
            'cover_credit' => sanitize_text_field(wp_unslash((string) ($_POST['cover_credit'] ?? ''))),
            'cover_creator' => sanitize_text_field(wp_unslash((string) ($_POST['cover_creator'] ?? ''))),
            'cover_source_url' => esc_url_raw(wp_unslash((string) ($_POST['cover_source_url'] ?? ''))),
            'cover_rights' => sanitize_key((string) ($_POST['cover_rights'] ?? 'unknown')),
            'cover_rights_note' => sanitize_textarea_field(wp_unslash((string) ($_POST['cover_rights_note'] ?? ''))),
            'cover_display' => !empty($_POST['cover_display']),
            'source_ref' => 'wordpress-admin-form',
        ];

        try {
            $result = Importer::import_album_row($row, $publish);
            $duplicate = !empty($result['duplicate_review']);

            if ($duplicate) {
                self::redirect(
                    sprintf(
                        '%s — %s was already saved with this exact review. No duplicate created.',
                        $artist,
                        $album
                    )
                );
            }

            self::redirect(
                sprintf(
                    '%s — %s saved as %s.',
                    $artist,
                    $album,
                    $publish ? 'published' : 'draft'
                )
            );
        } catch (\Throwable $e) {
            self::redirect('Could not save album: ' . $e->getMessage(), 'error');
        }
    }

    public static function handle_import(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }

        check_admin_referer('suzy_world_import');

        if (empty($_FILES['sw_import_file']['tmp_name'])) {
            self::redirect('No file uploaded.', 'error');
        }

        $file = $_FILES['sw_import_file'];
        $filename = sanitize_file_name((string) ($file['name'] ?? ''));
        $tmp = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, ['csv', 'json'], true)) {
            self::redirect('Only CSV and JSON are accepted.', 'error');
        }

        if (!is_uploaded_file($tmp)) {
            self::redirect('Upload validation failed.', 'error');
        }

        try {
            $result = Importer::import_file($tmp, $filename, !empty($_POST['publish']));
            self::redirect(
                sprintf(
                    'Import complete: %d inserted, %d duplicates, %d errors.',
                    (int) $result['inserted'],
                    (int) $result['duplicates'],
                    (int) $result['errors']
                ),
                (int) $result['errors'] > 0 ? 'error' : 'success'
            );
        } catch (\Throwable $e) {
            self::redirect('Import failed: ' . $e->getMessage(), 'error');
        }
    }

    private static function redirect(string $message, string $type = 'success'): void {
        $url = add_query_arg(
            [
                'page' => 'suzy-world',
                'sw_message' => $message,
                'sw_type' => $type,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
