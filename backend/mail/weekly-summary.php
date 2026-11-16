<?php

declare(strict_types=1);

/**
 * Weekly summary email (Italian: emails are rendered server side, outside the frontend i18n).
 *
 * @var string $familyName
 * @var string $from
 * @var string $to
 * @var list<array<string, mixed>> $children
 */
$date = fn (string $d): string => (new DateTimeImmutable($d))->format('d/m');
?>
<!doctype html>
<html lang="it">
<body style="font-family: system-ui, sans-serif; color: #1f1d1a;">
  <h1 style="color: #e07400;">HomeQuest · settimana <?= $date($from) ?>–<?= $date($to) ?></h1>
  <p>Ecco com'è andata la settimana della famiglia <?= htmlspecialchars($familyName) ?>.</p>
  <?php foreach ($children as $c): ?>
    <h2><?= htmlspecialchars((string) $c['child']['name']) ?></h2>
    <ul>
      <li>Compiti completati: <strong><?= (int) $c['tasks']['approved'] ?></strong></li>
      <li>Compiti saltati: <?= (int) $c['tasks']['missed'] ?></li>
      <?php if ($c['completion_rate'] !== null): ?>
        <li>Completamento routine: <?= (int) round($c['completion_rate'] * 100) ?>%</li>
      <?php endif ?>
      <li>Punti guadagnati: <?= (int) $c['points_earned'] ?> · spesi: <?= (int) $c['points_spent'] ?></li>
      <li>Serie attuale: <?= (int) $c['current_streak'] ?> giorni (record <?= (int) $c['best_streak'] ?>)</li>
    </ul>
  <?php endforeach ?>
  <p style="color: #5f5a54; font-size: 12px;">Puoi disattivare questa email dalle impostazioni della famiglia.</p>
</body>
</html>
