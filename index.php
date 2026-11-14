<?php
$prodotto = '';
$prezzo = '';
$sconto = '';
$errori = [];
$risultato = null;
$file_log = 'log.csv';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupero dati inviati (mantenuti per ripopolare i campi)
    $prodotto = trim($_POST['prodotto'] ?? '');
    $prezzo = trim($_POST['prezzo'] ?? '');
    $sconto = trim($_POST['sconto'] ?? '');

    // 1. Validazione Prodotto (obbligatorio, massimo 30 caratteri)
    if ($prodotto === '') {
        $errori['prodotto'] = 'Il nome del prodotto è obbligatorio.';
    } elseif (mb_strlen($prodotto) > 30) {
        $errori['prodotto'] = 'Il prodotto non può superare i 30 caratteri.';
    }

    // 2. Validazione Prezzo (numero maggiore di 0)
    $prezzo_norm = str_replace(',', '.', $prezzo);
    if ($prezzo === '' || !is_numeric($prezzo_norm) || (float)$prezzo_norm <= 0) {
        $errori['prezzo'] = 'Inserisci un prezzo numerico maggiore di 0.';
    }

    // 3. Validazione Sconto (numero intero da 1 a 90)
    if ($sconto === '' || filter_var($sconto, FILTER_VALIDATE_INT) === false || (int)$sconto < 1 || (int)$sconto > 90) {
        $errori['sconto'] = 'Inserisci uno sconto intero compreso tra 1 e 90.';
    }

    // Se non ci sono errori, esegui il calcolo e salva nel log
    if (empty($errori)) {
        $prezzo_val = (float)$prezzo_norm;
        $sconto_val = (int)$sconto;

        $risparmio = $prezzo_val * ($sconto_val / 100);
        $prezzo_finale = $prezzo_val - $risparmio;

        // Determinazione fascia sconto
        if ($sconto_val < 20) {
            $fascia = 'PICCOLO';
            $classe_card = 'card-piccolo';
        } elseif ($sconto_val <= 49) {
            $fascia = 'BUONO';
            $classe_card = 'card-buono';
        } else {
            $fascia = 'SUPER';
            $classe_card = 'card-super';
        }

        $risultato = [
            'prodotto' => $prodotto,
            'prezzo_iniziale' => number_format($prezzo_val, 2, ',', '.'),
            'sconto' => $sconto_val,
            'risparmio' => number_format($risparmio, 2, ',', '.'),
            'prezzo_finale' => number_format($prezzo_finale, 2, ',', '.'),
            'fascia' => $fascia,
            'classe' => $classe_card
        ];

        // Salvataggio su file CSV (crea intestazione se non esiste)
        $nuovo_file = !file_exists($file_log) || filesize($file_log) === 0;
        if (($fp = fopen($file_log, 'a')) !== false) {
            if ($nuovo_file) {
                fputcsv($fp, ['Data', 'Prodotto', 'Prezzo Iniziale', 'Sconto %', 'Risparmio', 'Prezzo Finale', 'Fascia'], ';');
            }
            fputcsv($fp, [
                date('Y-m-d H:i:s'),
                $prodotto,
                number_format($prezzo_val, 2, '.', ''),
                $sconto_val,
                number_format($risparmio, 2, '.', ''),
                number_format($prezzo_finale, 2, '.', ''),
                $fascia
            ], ';');
            fclose($fp);
        }
    }
}

// Lettura dello storico dal file log.csv
$storico = [];
if (file_exists($file_log) && ($fp = fopen($file_log, 'r')) !== false) {
    $riga_intestazione = fgetcsv($fp, 0, ';'); // Salta l'intestazione
    while (($riga = fgetcsv($fp, 0, ';')) !== false) {
        if (count($riga) >= 7) {
            $storico[] = $riga;
        }
    }
    fclose($fp);
    $storico = array_reverse($storico); // Mostra i più recenti in alto
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calcolo Sconto</title>
    <link rel="stylesheet" href="stile.css">
</head>
<body>
    <div class="container">
        <h1>Calcolatore Sconti</h1>

        <!-- Form senza validazione HTML5 per garantire controlli esclusivamente lato server -->
        <form method="POST" action="index.php" novalidate class="form-box">
            <div class="campo">
                <label for="prodotto">Prodotto (max 30 caratteri)</label>
                <input type="text" id="prodotto" name="prodotto" value="<?= htmlspecialchars($prodotto) ?>">
                <?php if (isset($errori['prodotto'])): ?>
                    <span class="errore"><?= htmlspecialchars($errori['prodotto']) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="prezzo">Prezzo (€)</label>
                <input type="text" id="prezzo" name="prezzo" value="<?= htmlspecialchars($prezzo) ?>">
                <?php if (isset($errori['prezzo'])): ?>
                    <span class="errore"><?= htmlspecialchars($errori['prezzo']) ?></span>
                <?php endif; ?>
            </div>

            <div class="campo">
                <label for="sconto">Sconto (1 - 90%)</label>
                <input type="text" id="sconto" name="sconto" value="<?= htmlspecialchars($sconto) ?>">
                <?php if (isset($errori['sconto'])): ?>
                    <span class="errore"><?= htmlspecialchars($errori['sconto']) ?></span>
                <?php endif; ?>
            </div>

            <button type="submit">Calcola Sconto</button>
        </form>

        <!-- Card del risultato -->
        <?php if ($risultato !== null): ?>
            <div class="card <?= $risultato['classe'] ?>">
                <h2>Risultato per: <?= htmlspecialchars($risultato['prodotto']) ?></h2>
                <p><strong>Prezzo finale:</strong> € <?= $risultato['prezzo_finale'] ?></p>
                <p><strong>Risparmio:</strong> € <?= $risultato['risparmio'] ?> (<?= $risultato['sconto'] ?>%)</p>
                <p class="badge">Fascia sconto: <?= $risultato['fascia'] ?></p>
            </div>
        <?php endif; ?>

        <!-- Storico dei calcoli -->
        <div class="storico">
            <h2>Storico Calcoli</h2>
            <?php if (empty($storico)): ?>
                <p>Nessun calcolo salvato nello storico.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Prodotto</th>
                            <th>Prezzo (€)</th>
                            <th>Sconto</th>
                            <th>Risparmio (€)</th>
                            <th>Finale (€)</th>
                            <th>Fascia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($storico as $riga): ?>
                            <tr>
                                <td><?= htmlspecialchars($riga[0]) ?></td>
                                <td><?= htmlspecialchars($riga[1]) ?></td>
                                <td><?= htmlspecialchars($riga[2]) ?></td>
                                <td><?= htmlspecialchars($riga[3]) ?>%</td>
                                <td><?= htmlspecialchars($riga[4]) ?></td>
                                <td><strong><?= htmlspecialchars($riga[5]) ?></strong></td>
                                <td><?= htmlspecialchars($riga[6]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
