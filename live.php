<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/site-bootstrap.php';

/**
 * /live.php — live streaming player. Additive feature: no local database
 * involvement, all match/stream data + admin management stays on
 * sagagoal.com (source of truth). This page just fetches one match
 * server-side from sagagoal.com's public API and renders it.
 *
 * Routing (.htaccess):
 *   /live/{id}/{slug}          -> live.php?id={id}&slug={slug}
 *   /live/custom/{id}/{slug}   -> live.php?custom_id={id}&slug={slug}
 * {slug} is cosmetic only (not looked up), matching the sagagoal.com
 * contract this page consumes.
 */

const WPM_LIVE_API_URL = 'https://sagagoal.com/api/live-match.php';
const WPM_LIVE_FETCH_TIMEOUT = 8;

/** Fetch one match/stream from sagagoal.com's live-match API. Server-side
 * only (no CORS on that endpoint by design) — never call this from JS. */
function wpm_live_fetch_match(?int $id, ?int $customId): array
{
    if ($customId !== null) {
        $query = ['custom_id' => $customId];
    } elseif ($id !== null) {
        $query = ['id' => $id];
    } else {
        return ['success' => false, 'message' => 'ID pertandingan tidak valid.'];
    }

    $ch = curl_init(WPM_LIVE_API_URL . '?' . http_build_query($query));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => WPM_LIVE_FETCH_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => WPM_LIVE_FETCH_TIMEOUT,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'BolaBolaBola-LivePlayer/1.0',
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('[wpm_live_fetch_match] cURL error: ' . $curlError);
        return ['success' => false, 'message' => 'Gagal menghubungi server streaming. Coba lagi sebentar.'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || !isset($decoded['success'])) {
        error_log('[wpm_live_fetch_match] Invalid API response: ' . substr($response, 0, 300));
        return ['success' => false, 'message' => 'Respons server streaming tidak valid.'];
    }

    if (empty($decoded['success']) || !isset($decoded['data']) || !is_array($decoded['data'])) {
        return ['success' => false, 'message' => (string) ($decoded['message'] ?? 'Pertandingan tidak ditemukan atau sedang tidak live.')];
    }

    return ['success' => true, 'data' => $decoded['data']];
}

$rawId = $_GET['id'] ?? null;
$rawCustomId = $_GET['custom_id'] ?? null;

$id = (is_string($rawId) && ctype_digit($rawId) && (int) $rawId > 0) ? (int) $rawId : null;
$customId = (is_string($rawCustomId) && ctype_digit($rawCustomId) && (int) $rawCustomId > 0) ? (int) $rawCustomId : null;

$result = wpm_live_fetch_match($id, $customId);
$match = $result['success'] ? $result['data'] : null;

$homeName = trim((string) ($match['home_name'] ?? ''));
$awayName = trim((string) ($match['away_name'] ?? ''));
$streamTitle = trim((string) ($match['stream_title'] ?? ''));
$displayTitle = $streamTitle !== '' ? $streamTitle : (($homeName !== '' && $awayName !== '') ? ($homeName . ' vs ' . $awayName) : 'Live Streaming');

$pageTitle = $match ? ($displayTitle . ' — ' . WPM_SITE_NAME) : ('Live Streaming — ' . WPM_SITE_NAME);
$metaDescription = trim((string) ($match['stream_description'] ?? '')) ?: ('Nonton siaran langsung di ' . WPM_SITE_NAME . '.');
$breadcrumbLabel = 'Live Streaming';
require __DIR__ . '/includes/site-header.php';
?>

<main class="wrap live-page">
<?php if ($match): ?>
  <?php
    $hasScore = array_key_exists('home_score', $match) && array_key_exists('away_score', $match)
        && $match['home_score'] !== null && $match['away_score'] !== null;
    $hasStatus = !empty($match['status_short']);
    $hasElapsed = isset($match['elapsed']) && $match['elapsed'] !== null && $match['elapsed'] !== '';
  ?>
  <div class="live-badge"><span class="dot"></span>Sedang Live</div>
  <?php if (!empty($match['league_name'])): ?><div class="live-league"><?= wpm_esc((string) $match['league_name']) ?></div><?php endif; ?>
  <h1 class="live-title"><?= wpm_esc($displayTitle) ?></h1>

  <?php if ($homeName !== '' || $awayName !== ''): ?>
  <div class="live-teams">
    <div class="live-team">
      <?php if (!empty($match['home_logo'])): ?><img src="<?= wpm_esc((string) $match['home_logo']) ?>" alt="<?= wpm_esc($homeName) ?>" class="live-team__logo" loading="lazy"><?php endif; ?>
      <span class="live-team__name"><?= wpm_esc($homeName) ?></span>
    </div>
    <div class="live-score <?= $hasScore ? '' : 'live-score--vs' ?>">
      <?php if ($hasScore): ?>
        <?= (int) $match['home_score'] ?> &ndash; <?= (int) $match['away_score'] ?>
        <?php if ($hasStatus): ?><span class="live-status"><?= wpm_esc((string) $match['status_short']) ?><?= $hasElapsed ? ' &middot; ' . (int) $match['elapsed'] . "'" : '' ?></span><?php endif; ?>
      <?php else: ?>
        VS
      <?php endif; ?>
    </div>
    <div class="live-team">
      <?php if (!empty($match['away_logo'])): ?><img src="<?= wpm_esc((string) $match['away_logo']) ?>" alt="<?= wpm_esc($awayName) ?>" class="live-team__logo" loading="lazy"><?php endif; ?>
      <span class="live-team__name"><?= wpm_esc($awayName) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <div class="live-video-wrap">
    <?= (string) ($match['embed_code'] ?? '') ?>
  </div>

  <?php if (!empty($match['stream_description'])): ?>
  <p class="live-desc"><?= wpm_esc((string) $match['stream_description']) ?></p>
  <?php endif; ?>

<?php else: ?>
  <div class="wpm-empty-state">
    <span class="wpm-empty-state__icon" aria-hidden="true">&#128250;</span>
    <h2>Siaran Tidak Ditemukan</h2>
    <p><?= wpm_esc($result['message'] ?? 'Pertandingan tidak ditemukan atau sedang tidak live.') ?></p>
    <a href="<?= wpm_esc(wpm_base_url('/')) ?>" class="cta">Kembali ke Beranda</a>
  </div>
<?php endif; ?>
</main>

<?php require __DIR__ . '/includes/site-footer.php'; ?>
