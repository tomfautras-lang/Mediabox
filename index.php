<?php
/*
 * index.php — Liste des ressources (documents et favoris)
 * avec filtre par mots-clés.
 */
require_once "includes/fonctions.php";

// 1. Mots-clés saisis dans le filtre.
//    Le formulaire utilise GET : l'URL filtrée peut être copiée ou mise en favori.
$saisie = $_GET["mots-cles"] ?? "";
if (!is_string($saisie)) {
    $saisie = ""; // protection contre ?mots-cles[]=... (tableau au lieu d'une chaîne)
}
if (!mb_check_encoding($saisie, "UTF-8")) {
    $saisie = mb_convert_encoding($saisie, "UTF-8", "UTF-8"); // octets invalides -> "?"
}
$saisie = trim($saisie);

// 2. Lecture des ressources : lire() reçoit le TABLEAU des mots saisis
//    (découpage sur les virgules) et ne garde que les ressources correspondantes.
$motsSaisis = explode(",", $saisie);
$ressources = lire($motsSaisis);

// Le filtre est "actif" s'il reste au moins un vrai mot après nettoyage
// (une saisie comme " , ,, " équivaut à une saisie vide).
$filtreActif = !empty(nettoyerMotsCles($motsSaisis));

// 3. Message de confirmation après un ajout, une modification ou une suppression.
//    Seuls les codes prévus sont acceptés : on n'affiche jamais un texte venant de l'URL.
$messagesPossibles = [
    "ajout"        => "La ressource a été ajoutée.",
    "modification" => "La ressource a été modifiée.",
    "suppression"  => "La ressource a été supprimée.",
];
$codeMessage = $_GET["message"] ?? "";
$messageSucces = is_string($codeMessage) ? ($messagesPossibles[$codeMessage] ?? "") : "";

if ($messageSucces !== "" && isset($_GET["guillemets"])) {
    $messageSucces .= ' Les guillemets droits (") saisis ont été convertis automatiquement, '
        . "car ce caractère sert de délimiteur dans le fichier de données.";
}

// 4. Petit bilan affiché au-dessus de la liste
$nbDocuments = count(array_filter($ressources, fn($r) => ($r["type"] ?? "") === "document"));
$nbFavoris = count($ressources) - $nbDocuments;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaBox – Mes ressources</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <header class="entete">
        <div class="entete-contenu">
            <a class="logo" href="index.php">
                <span class="logo-icone" aria-hidden="true">🏎️</span>
                <span><strong>MediaBox</strong><small>Le garage partagé du club</small></span>
            </a>
            <a class="bouton bouton-clair" href="ajouter.php">＋ Ajouter une ressource</a>
        </div>
    </header>

    <main>
        <h1 class="visuellement-cache">Mes ressources</h1>

        <?php if ($messageSucces !== ""): ?>
            <div class="message message-succes" role="status">
                <p><?= echapper($messageSucces) ?></p>
            </div>
        <?php endif; ?>

        <!-- Filtre par mots-clés -->
        <form class="filtre" method="get" action="index.php" role="search">
            <label for="mots-cles">Filtrer par mots-clés</label>
            <div class="filtre-ligne">
                <input type="search" id="mots-cles" name="mots-cles"
                       value="<?= echapper($saisie) ?>"
                       placeholder="ex. : sportive, électrique"
                       aria-describedby="aide-filtre">
                <button type="submit" class="bouton">Filtrer</button>
                <a class="bouton bouton-secondaire" href="index.php">Réinitialiser</a>
            </div>
            <p class="aide" id="aide-filtre">
                Séparez les mots-clés par des virgules : seules les ressources possédant
                <strong>tous</strong> ces mots-clés sont affichées. Majuscules et accents sont ignorés.
            </p>
        </form>

        <?php if (empty($ressources)): ?>

            <!-- Aucun résultat -->
            <div class="aucun-resultat">
                <?php if ($filtreActif): ?>
                    <p>Aucun résultat</p>
                    <p>Aucune ressource ne possède tous les mots-clés « <?= echapper($saisie) ?> ».</p>
                    <p><a class="bouton bouton-secondaire" href="index.php">Afficher toutes les ressources</a></p>
                <?php else: ?>
                    <p>Aucune ressource pour le moment</p>
                    <p><a class="bouton" href="ajouter.php">Ajouter la première ressource</a></p>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <p class="bilan">
                <?= count($ressources) ?> ressource<?= count($ressources) > 1 ? "s" : "" ?>
                (<?= $nbDocuments ?> document<?= $nbDocuments > 1 ? "s" : "" ?>,
                <?= $nbFavoris ?> favori<?= $nbFavoris > 1 ? "s" : "" ?>)
                <?php if ($filtreActif): ?>
                    pour « <?= echapper($saisie) ?> »
                <?php endif; ?>
            </p>

            <ul class="liste-ressources">
                <?php foreach ($ressources as $ressource): ?>
                    <?php
                    // Préparation des informations de la ressource courante
                    $id          = $ressource["id"] ?? "";
                    $estDocument = ($ressource["type"] ?? "") === "document";
                    $date        = $ressource["date"] ?? "";
                    // Mots-clés à afficher : sans espaces superflus, sans vide ni doublon
                    $motsCles    = array_filter(
                        explode(", ", formaterMotsCles($ressource["mots-cles"] ?? "")),
                        fn($mot) => $mot !== ""
                    );

                    if ($estDocument) {
                        // Document : lien vers le fichier du dossier uploads/ (basename : pas de "../")
                        $nomFichier    = basename($ressource["fichier"] ?? "");
                        $cheminFichier = DOSSIER_UPLOADS . $nomFichier;
                        $lienValide    = $nomFichier !== "" && is_file($cheminFichier);
                        $lienVoir      = DOSSIER_UPLOADS . rawurlencode($nomFichier);
                        $extension     = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
                    } else {
                        // Favori : on n'affiche le lien que si l'URL est sûre (http/https)
                        $url        = $ressource["url"] ?? "";
                        $lienValide = urlValide($url);
                        $lienVoir   = $url;
                    }
                    ?>
                    <li>
                        <article class="ressource <?= $estDocument ? "document" : "favori" ?>">
                            <div class="ressource-entete">
                                <?php if ($estDocument): ?>
                                    <span class="badge badge-document"><span aria-hidden="true">📄</span> Document</span>
                                <?php else: ?>
                                    <span class="badge badge-favori"><span aria-hidden="true">🔗</span> Favori</span>
                                <?php endif; ?>

                                <?php if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)): ?>
                                    <time datetime="<?= echapper($date) ?>"><?= echapper(formaterDate($date)) ?></time>
                                <?php endif; ?>
                            </div>

                            <h2><?= echapper($ressource["titre"] ?? "(sans titre)") ?></h2>

                            <!-- Illustration de la ressource (sauf si le document est lui-même une photo) -->
                            <?php if (!($estDocument && $lienValide && in_array($extension, ["jpg", "jpeg", "png", "gif"], true))): ?>
                                <?php
                                $nomImage   = basename($ressource["image"] ?? "");
                                $imageCarte = ($nomImage !== "" && is_file("images/" . $nomImage))
                                    ? $nomImage
                                    : ($estDocument ? "defaut-document.jpg" : "defaut-favori.jpg");
                                ?>
                                <img class="apercu-image" src="images/<?= echapper(rawurlencode($imageCarte)) ?>" alt=""
                                     width="600" height="340" loading="lazy">
                            <?php endif; ?>

                            <!-- Aperçu directement dans la liste : photo, enregistrement audio ou vidéo -->
                            <?php if ($estDocument && $lienValide): ?>
                                <?php if (in_array($extension, ["jpg", "jpeg", "png", "gif"], true)): ?>
                                    <?php $dimensions = @getimagesize($cheminFichier); // largeur/hauteur : évite les sauts de mise en page ?>
                                    <img class="apercu-image" src="<?= echapper($lienVoir) ?>"
                                         alt="Aperçu de « <?= echapper($ressource["titre"] ?? "") ?> »" loading="lazy"
                                         <?= $dimensions ? 'width="' . $dimensions[0] . '" height="' . $dimensions[1] . '"' : "" ?>>
                                <?php elseif ($extension === "mp3"): ?>
                                    <audio class="apercu-media" controls preload="none" src="<?= echapper($lienVoir) ?>"
                                           aria-label="Écouter « <?= echapper($ressource["titre"] ?? "") ?> »">
                                        <a href="<?= echapper($lienVoir) ?>">Télécharger l'enregistrement</a>
                                    </audio>
                                <?php elseif ($extension === "mp4"): ?>
                                    <video class="apercu-media" controls preload="metadata" src="<?= echapper($lienVoir) ?>"
                                           aria-label="Regarder « <?= echapper($ressource["titre"] ?? "") ?> »">
                                        <a href="<?= echapper($lienVoir) ?>">Télécharger la vidéo</a>
                                    </video>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (($ressource["description"] ?? "") !== ""): ?>
                                <p class="description"><?= nl2br(echapper($ressource["description"]), false) ?></p>
                            <?php endif; ?>

                            <!-- Cible : fichier (type + taille) ou site web -->
                            <?php if ($estDocument && $lienValide): ?>
                                <p class="cible">
                                    Fichier <?= echapper(strtoupper($extension)) ?>
                                    · <?= formaterTaille(filesize($cheminFichier)) ?>
                                </p>
                            <?php elseif ($estDocument): ?>
                                <p class="cible alerte">Fichier introuvable sur le serveur</p>
                            <?php elseif ($lienValide): ?>
                                <p class="cible"><?= echapper(parse_url(str_replace("&quot;", '"', $url), PHP_URL_HOST) ?? "") ?></p>
                            <?php else: ?>
                                <p class="cible alerte">Adresse web invalide</p>
                            <?php endif; ?>

                            <!-- Mots-clés : chaque mot est un lien qui filtre la liste -->
                            <?php if (!empty($motsCles)): ?>
                                <ul class="mots-cles">
                                    <?php foreach ($motsCles as $motCle): ?>
                                        <li><a href="index.php?mots-cles=<?= urlencode(str_replace("&quot;", '"', $motCle)) ?>"
                                               title="Afficher les ressources ayant ce mot-clé"><?= echapper($motCle) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="sans-mot-cle">Aucun mot-clé</p>
                            <?php endif; ?>

                            <!-- Actions -->
                            <div class="actions">
                                <?php if ($lienValide && $estDocument): ?>
                                    <a class="action-voir" href="<?= echapper($lienVoir) ?>" target="_blank"
                                       title="Ouvrir le fichier dans un nouvel onglet">Voir</a>
                                <?php elseif ($lienValide): ?>
                                    <a class="action-voir" href="<?= echapper($lienVoir) ?>" target="_blank"
                                       rel="noopener noreferrer" title="Ouvrir le site dans un nouvel onglet">Voir</a>
                                <?php endif; ?>
                                <a href="modifier.php?id=<?= urlencode((string) $id) ?>">Modifier</a>
                                <a class="action-supprimer" href="supprimer.php?id=<?= urlencode((string) $id) ?>">Supprimer</a>
                            </div>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php endif; ?>
    </main>

    <footer class="pied">
        <p>MediaBox – prototype de GED pour OrgaPlus · club automobile · données stockées dans data/ressources.txt</p>
    </footer>
</body>
</html>
