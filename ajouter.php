<?php
/*
 * ajouter.php — Formulaire unique d'ajout d'un document ou d'un favori.
 */

// 1. Initialisation
require_once "includes/fonctions.php";

$erreurs = [];          // messages d'erreur à afficher
$type = "document";     // valeurs par défaut du formulaire
$titre = "";
$motsCles = "";
$description = "";
$url = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (requeteTropVolumineuse()) {
        // PHP a vidé $_POST et $_FILES : le fichier dépasse post_max_size
        $erreurs[] = "Les données envoyées dépassent la taille maximale acceptée par le serveur ("
            . ini_get("post_max_size") . "). Choisissez un fichier plus léger.";
    } else {

        // 2. Récupérer les saisies du formulaire
        //    (pour pouvoir les réafficher en cas d'erreur)
        $type        = lireChampPost("type");
        $titre       = lireChampPost("titre");
        $motsCles    = lireChampPost("mots-cles");
        $description = lireChampPost("description");
        $url         = lireChampPost("url");
        $fichier     = $_FILES["fichier"] ?? null;
        $fichierAccepte = false; // devient true si le fichier passe les contrôles

        // 3. Vérifier les saisies
        //    - champs obligatoires selon le type de ressource
        //    - extension du fichier (si document)
        //    Chaque problème ajoute un message au tableau des erreurs.
        if (nettoyerTexte($titre) === "") {
            $erreurs[] = "Le titre est obligatoire.";
        } elseif (mb_strlen($titre) > 150) {
            $erreurs[] = "Le titre ne doit pas dépasser 150 caractères.";
        }

        if ($type != "document" && $type != "favori") {
            $erreurs[] = "Le type de ressource est invalide : choisissez « Document » ou « Favori ».";
            $type = "document"; // pour réafficher le formulaire dans un état cohérent
        } elseif ($type == "document") {
            $erreurFichier = verifierFichier($fichier);
            $fichierAccepte = ($erreurFichier === null);
            if (!$fichierAccepte) {
                $erreurs[] = $erreurFichier;
            }
        } else {
            if ($url === "") {
                $erreurs[] = "L'adresse (URL) est obligatoire pour un favori.";
            } elseif (!urlValide($url)) {
                $erreurs[] = "L'adresse doit commencer par http:// ou https:// et ne contenir "
                    . "ni espace ni guillemet (ex. : https://www.php.net).";
            }
        }

        if (!is_writable(FICHIER_GED)) {
            $erreurs[] = "Enregistrement impossible : le fichier " . FICHIER_GED . " n'est pas accessible en écriture.";
        }

        // 4. S'il n'y a aucune erreur :
        //    - téléverser le fichier (si document)
        //    - enregistrer la ressource
        //    - rediriger vers la liste
        //    Sinon : on continue, la page se réaffiche avec les erreurs.
        if (empty($erreurs)) {

            // Le guillemet droit est remplacé par &quot; (voir nettoyerTexte) : on prévient l'utilisateur
            $guillemetsConvertis = str_contains($titre . $motsCles . $description, '"');

            // L'ordre des clés est celui du fichier : id (ajouté par ajouter()), type, titre...
            $ressource = [
                "type"        => $type,
                "titre"       => nettoyerTexte($titre),
                "mots-cles"   => nettoyerTexte(formaterMotsCles($motsCles)),
                "description" => nettoyerTexte($description, true),
            ];

            if ($type == "document") {
                $nomFichier = enregistrerFichier($fichier); // nom généré côté serveur
                if ($nomFichier === null) {
                    $erreurs[] = "Le fichier n'a pas pu être enregistré dans le dossier uploads/.";
                } else {
                    $ressource["fichier"] = $nomFichier;
                }
            } else {
                $ressource["url"] = nettoyerTexte($url);
            }
            $ressource["date"] = date("Y-m-d");

            if (empty($erreurs)) {
                ajouter($ressource);
                header("Location: index.php?message=ajout" . ($guillemetsConvertis ? "&guillemets=1" : ""));
                exit;
            }
        }

        // Le fichier était correct mais une autre erreur bloque l'enregistrement :
        // le navigateur ne peut pas réafficher un fichier choisi, on le signale.
        if (!empty($erreurs) && $type == "document" && $fichierAccepte) {
            $erreurs[] = "Par sécurité, le navigateur ne conserve pas le fichier choisi : sélectionnez-le à nouveau.";
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MediaBox – Ajouter une ressource</title>
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
        <div class="carte">
            <h1>Ajouter une ressource</h1>
            <p class="aide">Les champs marqués d'un <span class="obligatoire">*</span> sont obligatoires.</p>

            <!-- Afficher les erreurs s'il y en a -->
            <?php if (!empty($erreurs)): ?>
                <div class="message message-erreur" role="alert">
                    <p><strong>Le formulaire n'a pas été enregistré :</strong></p>
                    <ul>
                        <?php foreach ($erreurs as $erreur): ?>
                            <li><?= echapper($erreur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Un seul formulaire pour les deux types ; enctype obligatoire pour l'envoi de fichier -->
            <form method="post" action="ajouter.php" enctype="multipart/form-data">

                <fieldset class="choix-type">
                    <legend>Type de ressource <span class="obligatoire">*</span></legend>
                    <div class="options-type">
                        <label class="option-type">
                            <input type="radio" name="type" value="document" required
                                <?= $type == "document" ? "checked" : "" ?>>
                            <span><span aria-hidden="true">📄</span> Document
                                <small>Photo, fiche technique, vidéo d'essai, bruit moteur…</small></span>
                        </label>
                        <label class="option-type">
                            <input type="radio" name="type" value="favori"
                                <?= $type == "favori" ? "checked" : "" ?>>
                            <span><span aria-hidden="true">🔗</span> Favori
                                <small>Annonce, essai en ligne, site constructeur, YouTube…</small></span>
                        </label>
                    </div>
                </fieldset>

                <div class="champ">
                    <label for="titre">Titre <span class="obligatoire">*</span></label>
                    <input type="text" id="titre" name="titre" maxlength="150" required
                           value="<?= echapper($titre) ?>">
                </div>

                <!-- Champ affiché pour un document -->
                <div class="champ" id="bloc-fichier">
                    <label for="fichier">Fichier <span class="obligatoire">*</span></label>
                    <input type="file" id="fichier" name="fichier" aria-describedby="aide-fichier"
                           accept=".<?= implode(",.", EXTENSIONS_AUTORISEES) ?>">
                    <p class="aide" id="aide-fichier">
                        Formats acceptés : <?= implode(", ", EXTENSIONS_AUTORISEES) ?>
                        — taille maximale : <?= echapper(ini_get("upload_max_filesize")) ?>.
                    </p>
                </div>

                <!-- Champ affiché pour un favori -->
                <div class="champ" id="bloc-url">
                    <label for="url">Adresse du site (URL) <span class="obligatoire">*</span></label>
                    <input type="url" id="url" name="url" placeholder="https://…"
                           value="<?= echapper($url) ?>">
                </div>

                <div class="champ">
                    <label for="mots-cles">Mots-clés</label>
                    <input type="text" id="mots-cles" name="mots-cles" aria-describedby="aide-mots-cles"
                           value="<?= echapper($motsCles) ?>">
                    <p class="aide" id="aide-mots-cles">Séparés par des virgules, par exemple : sportive, électrique, essai</p>
                </div>

                <div class="champ">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5"><?= echapper($description) ?></textarea>
                </div>

                <div class="boutons-formulaire">
                    <button type="submit" class="bouton">Enregistrer</button>
                    <a class="bouton bouton-secondaire" href="index.php">Annuler</a>
                </div>
            </form>
        </div>
    </main>

    <footer class="pied">
        <p>MediaBox – prototype de GED pour OrgaPlus · club automobile · données stockées dans data/ressources.txt</p>
    </footer>

    <!-- Script de bascule : affiche le champ fichier OU le champ URL selon le type choisi -->
    <script>
        const boutonsType = document.querySelectorAll('input[name="type"]');
        const blocFichier = document.getElementById("bloc-fichier");
        const blocUrl = document.getElementById("bloc-url");

        function basculerFormulaire() {
            const estDocument = document.querySelector('input[name="type"]:checked').value === "document";
            blocFichier.hidden = !estDocument;
            blocUrl.hidden = estDocument;
            // Le champ masqué est aussi désactivé : il n'est donc pas envoyé avec le formulaire
            document.getElementById("fichier").disabled = !estDocument;
            document.getElementById("url").disabled = estDocument;
        }

        boutonsType.forEach(function (bouton) {
            bouton.addEventListener("change", basculerFormulaire);
        });
        basculerFormulaire(); // état initial, y compris quand la page est réaffichée avec des erreurs
    </script>
</body>
</html>
