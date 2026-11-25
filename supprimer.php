<?php
/*
 * supprimer.php — Confirmation puis suppression d'une ressource.
 * Pour un document, le fichier du dossier uploads/ est aussi supprimé
 * (c'est la fonction supprimer() fournie qui s'en charge).
 */
require_once "includes/fonctions.php";

// 1. Vérification de l'identifiant reçu dans l'URL (?id=...)
$id = lireIdDepuisUrl();
$ressource = ($id !== null) ? getDocumentById($id) : null;

$erreur = "";

// 2. Traitement de la réponse : la suppression ne se fait qu'en POST,
//    jamais par un simple lien (un lien peut être suivi par erreur).
if ($ressource !== null && $_SERVER["REQUEST_METHOD"] == "POST") {

    if (lireChampPost("confirmation") === "oui") {
        if (supprimer($id)) {
            header("Location: index.php?message=suppression");
            exit;
        }
        $erreur = "La suppression a échoué : la ressource n'existe plus.";
    } else {
        // Bouton « Annuler » : retour à la liste sans rien modifier
        header("Location: index.php");
        exit;
    }
}

if ($ressource === null) {
    http_response_code(404);
}

$estDocument = ($ressource["type"] ?? "") === "document";
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaBox – Supprimer une ressource</title>
    <link rel="stylesheet" href="style/style.css">
</head>
<body>
    <header class="entete">
        <div class="entete-contenu">
            <a class="logo" href="index.php">
                <span class="logo-icone" aria-hidden="true">🏎️</span>
                <span><strong>MediaBox</strong><small>Le garage partagé du club</small></span>
            </a>
            <a class="bouton bouton-clair" href="index.php">← Retour à la liste</a>
        </div>
    </header>

    <main class="etroit">
        <div class="carte confirmation">
            <?php if ($ressource === null): ?>

                <h1>Ressource introuvable</h1>
                <div class="message message-erreur" role="alert">
                    <p>Aucune ressource ne correspond à cet identifiant : elle a peut-être déjà été supprimée,
                        ou le lien est incorrect.</p>
                </div>
                <p><a class="bouton" href="index.php">Retour à la liste des ressources</a></p>

            <?php else: ?>

                <h1>Supprimer <?= $estDocument ? "ce document" : "ce favori" ?> ?</h1>

                <?php if ($erreur !== ""): ?>
                    <div class="message message-erreur" role="alert">
                        <p><?= echapper($erreur) ?></p>
                    </div>
                <?php endif; ?>

                <p>Vous êtes sur le point de supprimer :</p>
                <p class="titre-ressource"><?= echapper($ressource["titre"] ?? "(sans titre)") ?></p>

                <?php if ($estDocument && !empty($ressource["fichier"])): ?>
                    <p>Le fichier <strong><?= echapper(basename($ressource["fichier"])) ?></strong>
                        sera également supprimé du serveur.</p>
                <?php endif; ?>

                <p><strong>Cette action est définitive.</strong> Confirmez-vous la suppression ?</p>

                <form method="post" action="supprimer.php?id=<?= urlencode((string) $id) ?>">
                    <div class="boutons-formulaire">
                        <button type="submit" name="confirmation" value="oui" class="bouton bouton-danger">Oui, supprimer</button>
                        <button type="submit" name="confirmation" value="non" class="bouton bouton-secondaire">Annuler</button>
                    </div>
                </form>

            <?php endif; ?>
        </div>
    </main>

    <footer class="pied">
        <p>MediaBox – prototype de GED pour OrgaPlus · club automobile · données stockées dans data/ressources.txt</p>
    </footer>
</body>
</html>
