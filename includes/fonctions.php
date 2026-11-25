<?php
/**
 * -----------------------------------------------------------
 * FONCTIONS GED - Stockage dans un fichier texte structuré
 * -----------------------------------------------------------
 * Format du fichier :
 *   clé:"valeur"
 *   (valeurs multilignes possibles)
 *   Une ligne vide sépare les ressources.
 * -----------------------------------------------------------
 */

const FICHIER_GED = 'data/ressources.txt';

/*******************************************************************
 * Lire tous les documents depuis le fichier texte.
 *
 * MODIFIÉE (TP) : ajout d'un filtre facultatif par mots-clés.
 *
 * @param array $motsCles  Liste des mots-clés recherchés, par exemple
 *                         ['php', ' Web '] ou explode(',', $saisie).
 *                         Chaque mot est normalisé (casse, accents,
 *                         espaces) ; les mots vides et les doublons
 *                         sont ignorés. Tableau vide (valeur par
 *                         défaut) = aucune restriction.
 * @return array  Les ressources dont le champ mots-cles contient
 *                TOUS les mots recherchés (logique ET, mot entier).
 *******************************************************************/

function lire(array $motsCles = []) {
    // Vérifie si le fichier existe, sinon affiche un message d'erreur
    if (!file_exists(FICHIER_GED)) {
        echo "ERREUR : le fichier de données ".FICHIER_GED." n'existe pas.<br>";
        exit;
    }

    // Lit toutes les lignes du fichier dans un tableau $lignes
    // FILE_IGNORE_NEW_LINES supprime le saut de ligne en fin de chaque élément
    $lignes = file(FICHIER_GED, FILE_IGNORE_NEW_LINES);

    $documents = []; // Tableau final qui contiendra tous les documents
    $doc = [];       // Tableau temporaire pour stocker un document en cours de lecture

    $nbLignes = count($lignes); // Nombre total de lignes dans le fichier
    $i = 0;                     // Indice pour parcourir les lignes avec une boucle while

    // Parcours de toutes les lignes
    while ($i < $nbLignes) {
        $ligne = trim($lignes[$i]); // Supprime les espaces et tabulations en début et fin de ligne

        // --- Ligne vide : fin d'un document ---
        if ($ligne === "") {
            if (!empty($doc)) {
                $documents[] = $doc; // Ajoute le document courant au tableau final
                $doc = [];           // Réinitialise le tableau pour le prochain document
            }
            $i++;       // Passe à la ligne suivante
            continue;   // Revient au début de la boucle pour traiter la ligne suivante
        }

        // Recherche la position du caractère ":" qui sépare clé et valeur
        $pos = strpos($ligne, ":");
		
		// Vérifie l'existence du séparateur ":" entre la clé et la valeur
		if(!preg_match('/^[^:" ]*:\s*"/', $ligne)){
			echo "ERREUR  -- syntaxe incorrecte à la ligne ".($i+1)." du fichier ".FICHIER_GED."<br>";
			exit;
		}

        // --- Extrait la clé et la valeur ---
        $cle = substr($ligne, 0, $pos);                 // Extrait la clé : tout avant ":"
        $valeur = trim(substr($ligne, $pos + 1));      // Extrait la valeur : tout après ":", et enlève les espaces

        // Supprime le guillemet d'ouverture si la valeur commence par "
        if (str_starts_with($valeur, '"')) {
            $valeur = substr($valeur, 1);
        }

    	while (
            ($i + 1) < $nbLignes &&                  // On n’a pas dépassé le nombre total de lignes
            trim($lignes[$i + 1]) !== "" &&          // La ligne suivante n’est pas vide
            str_ends_with($lignes[$i], '"') === false)   // La ligne courant ne se termine pas par "
		{
			$i++;                                      // Passe à la ligne suivante
			$valeur .= "\n" . trim($lignes[$i]);      // Concatène la ligne suivante à la valeur avec un saut de ligne
        }

        // Supprime le guillemet de fermeture à la fin de la valeur
        if (str_ends_with($valeur, '"')) {
            $valeur = substr($valeur, 0, -1);
        }

        // Ajoute la paire clé/valeur dans le tableau du document courant
        $doc[$cle] = $valeur;

        $i++; // Passe à la ligne suivante
    }

    // Ajoute le dernier document si le fichier ne se termine pas par une ligne vide
    if (!empty($doc)) {
        $documents[] = $doc;
    }

    // --- AJOUT TP : filtre par mots-clés ---
    // Nettoie les mots recherchés : normalisation (casse, accents, espaces),
    // suppression des mots vides (",,", "  ") et des doublons.
    $filtre = nettoyerMotsCles($motsCles);

    // Sans mot-clé exploitable, toutes les ressources sont retournées
    if (empty($filtre)) {
        return $documents;
    }

    $resultats = []; // Ressources qui correspondent à tous les mots du filtre

    foreach ($documents as $document) {
        // Mots-clés de la ressource, découpés et normalisés de la même façon
        $motsRessource = extraireMotsCles($document['mots-cles'] ?? '');

        // Logique ET : chaque mot du filtre doit être l'un des mots-clés de la ressource
        $toutTrouve = true;
        foreach ($filtre as $mot) {
            // in_array strict : égalité exacte, donc "php" ne correspond pas à "phpmyadmin"
            if (!in_array($mot, $motsRessource, true)) {
                $toutTrouve = false;
                break; // Inutile de tester les mots suivants
            }
        }

        if ($toutTrouve) {
            $resultats[] = $document;
        }
    }

    // Retourne uniquement les ressources retenues par le filtre
    return $resultats;
}


/*******************************************************************
 * Écrire tous les documents dans le fichier texte.
 *******************************************************************/
function ecrire(array $documents) {
    $sortie = ""; // Initialise une chaîne vide qui contiendra tout le contenu à écrire

    // Parcourt chaque document/favori dans le tableau
    foreach ($documents as $doc) {

        // Parcourt chaque clé/valeur du document
        foreach ($doc as $cle => $valeur) {
            // Normalise les retours chariot Windows (\r\n) en retours chariot Unix (\n)
            $valeur = str_replace("\r\n", "\n", $valeur);

            // Construit la ligne "clé:"valeur"" et l'ajoute à la chaîne de sortie
            $sortie .= $cle . ':"' . $valeur . '"' . "\n";
        }

        // Ajoute une ligne vide entre chaque document/favori
        $sortie .= "\n";
    }

    // Écrit tout le contenu dans le fichier défini par FICHIER_GED
    file_put_contents(FICHIER_GED, $sortie);
}


/*******************************************************************
 * Obtenir un document par son ID.
 *******************************************************************/
function getDocumentById($id) {
    // Lit tous les documents/favoris à partir du fichier texte
    $documents = lire();

    // Parcourt chaque document
    foreach ($documents as $doc) {
        // Vérifie si le document a une clé 'id' et si elle correspond à l'identifiant recherché
        if (isset($doc['id']) && $doc['id'] == $id) {
            return $doc; // Retourne le document correspondant dès qu'il est trouvé
        }
    }

    // Si aucun document avec cet ID n'a été trouvé, retourne null
    return null;
}


/*******************************************************************
 * Ajouter un document.
 *******************************************************************/
function ajouter(array $document) {
    // Lit tous les documents/favoris depuis le fichier
    $documents = lire();

    // Détermine le nouvel ID :
    $nouvelId = count($documents) ? max(array_column($documents, 'id')) + 1 : 1;

    // Assigne le nouvel ID au document
    $document['id'] = $nouvelId;

    // Réorganise le tableau pour que l'ID soit en première position
    if (isset($document['id'])) {
        $document = ['id' => $document['id']] + $document;
    }

    // Ajoute le document au tableau existant
    $documents[] = $document;

    // Réécrit tous les documents dans le fichier
    ecrire($documents);

    // Retourne l'ID du document ajouté
    return $nouvelId;
}



/*******************************************************************
 * Modifier un document existant.
 *******************************************************************/
function modifier($id, array $document) {
    // Lit tous les documents existants depuis le fichier
    $documents = lire();
    $modifie = false; // Indique si une modification a été effectuée

    // Parcourt tous les documents par référence pour pouvoir les modifier directement
    foreach ($documents as &$doc) {
        // Si le document courant a le même ID que celui à modifier
        if ($doc['id'] == $id) {
            // Parcourt toutes les clés/valeurs à mettre à jour
            foreach ($document as $cle => $valeur) {
                // Ne modifie jamais l'ID
                if ($cle !== 'id') $doc[$cle] = $valeur;
            }

            // Réorganise le tableau pour que l'ID reste en première position
            $doc = ['id' => $doc['id']] + $doc;

            $modifie = true; // Indique qu'une modification a été effectuée
            break;           // On a trouvé et modifié le document, on peut sortir
        }
    }

    // Si une modification a été faite, réécrit tous les documents dans le fichier
    if ($modifie) ecrire($documents);

    // Retourne true si un document a été modifié, false sinon
    return $modifie;
}



/*******************************************************************
 * Supprimer un document.
 *******************************************************************/
function supprimer($id) {
    // Lit tous les documents/favoris existants depuis le fichier
    $documents = lire();

    $nouvelleListe = []; // Tableau pour stocker les documents qui ne seront pas supprimés
    $supprime = false;   // Indique si un document a été supprimé

    // Parcourt tous les documents
    foreach ($documents as $doc) {
        // Si le document courant a l'ID à supprimer
        if ($doc['id'] == $id) {
            $supprime = true; // On marque qu'une suppression a eu lieu

            // Si le document contient un fichier associé
            if (!empty($doc['fichier'])) {
                $fichier = 'uploads/' . $doc['fichier']; // Chemin complet du fichier
                // Supprime physiquement le fichier s'il existe
                if (file_exists($fichier)) unlink($fichier);
            }

        } else {
            // Si le document n'a pas l'ID à supprimer, on le garde
            $nouvelleListe[] = $doc;
        }
    }

    // Si une suppression a été faite, on réécrit la liste mise à jour dans le fichier
    if ($supprime) ecrire($nouvelleListe);

    // Retourne true si un document a été supprimé, false sinon
    return $supprime;
}

/*******************************************************************
 * Normalise une chaîne pour permettre des comparaisons insensibles
 * à la casse, aux accents et aux espaces en début/fin.
 *******************************************************************/

function normaliser(string $texte): string
{
    $texte = trim($texte);
    $texte = mb_strtolower($texte, 'UTF-8');

    if (class_exists('Normalizer')) {
        // Décompose les caractères accentués (é -> e + accent), puis retire les accents
        $texte = Normalizer::normalize($texte, Normalizer::FORM_D);
        $texte = preg_replace('/\p{Mn}/u', '', $texte);
    } else {
        // Solution de repli si l'extension intl n'est pas disponible
        $accents = [
            'à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a',
            'ç'=>'c',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','í'=>'i',
            'ô'=>'o','ö'=>'o','ó'=>'o','õ'=>'o',
            'ù'=>'u','û'=>'u','ü'=>'u','ú'=>'u',
            'ÿ'=>'y','ñ'=>'n','œ'=>'oe','æ'=>'ae'
        ];
        $texte = strtr($texte, $accents);
    }

    return $texte;
}


/* =================================================================
 * FONCTIONS AJOUTÉES POUR LE TP
 * -----------------------------------------------------------------
 * Fonctions utilitaires partagées par les pages index, ajouter,
 * modifier et supprimer (évite de dupliquer le même code).
 * ================================================================= */

const DOSSIER_UPLOADS = 'uploads/';

// Liste blanche des extensions acceptées au téléversement
const EXTENSIONS_AUTORISEES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'mp3', 'mp4', 'txt', 'docx', 'odt'];


/*******************************************************************
 * Normalise UN mot-clé pour la comparaison : minuscules, sans
 * accents (via normaliser()), espaces multiples réduits à un seul.
 * Exemple : "  Mot   de PASSE " -> "mot de passe"
 *******************************************************************/
function normaliserMotCle(string $mot): string
{
    $mot = normaliser($mot);
    // \s+ avec le drapeau u : toute suite d'espaces (y compris insécables) -> un espace
    $mot = (string) preg_replace('/\s+/u', ' ', $mot);
    return trim($mot);
}


/*******************************************************************
 * Nettoie une liste de mots-clés : normalise chaque mot, retire les
 * mots vides et les doublons.
 * Exemple : ['PHP', ' ', 'php', 'Web'] -> ['php', 'web']
 *******************************************************************/
function nettoyerMotsCles(array $mots): array
{
    $resultat = [];
    foreach ($mots as $mot) {
        $mot = normaliserMotCle((string) $mot);
        if ($mot !== '' && !in_array($mot, $resultat, true)) {
            $resultat[] = $mot;
        }
    }
    return $resultat;
}


/*******************************************************************
 * Découpe une chaîne "mot1, mot2, ..." en tableau de mots-clés
 * normalisés (utilisée pour le champ mots-cles d'une ressource).
 *******************************************************************/
function extraireMotsCles(string $liste): array
{
    return nettoyerMotsCles(explode(',', $liste));
}


/*******************************************************************
 * Met en forme les mots-clés saisis avant enregistrement :
 * "  php ,, Web,web " -> "php, Web"
 * La casse et les accents saisis sont conservés pour l'affichage ;
 * les doublons sont détectés sans tenir compte de la casse/accents.
 *******************************************************************/
function formaterMotsCles(string $saisie): string
{
    $motsGardes = [];   // mots tels que saisis (nettoyés des espaces)
    $dejaVus = [];      // versions normalisées, pour repérer les doublons

    foreach (explode(',', $saisie) as $mot) {
        $mot = trim((string) preg_replace('/\s+/u', ' ', $mot));
        $motNormalise = normaliserMotCle($mot);
        if ($motNormalise !== '' && !in_array($motNormalise, $dejaVus, true)) {
            $motsGardes[] = $mot;
            $dejaVus[] = $motNormalise;
        }
    }
    return implode(', ', $motsGardes);
}


/*******************************************************************
 * Prépare un texte saisi pour qu'il respecte le format du fichier :
 *  - le guillemet droit " (délimiteur) est remplacé par &quot; ;
 *  - champ sur une ligne : sauts de ligne et espaces multiples
 *    remplacés par un seul espace ;
 *  - champ multiligne : les lignes vides sont supprimées, car lire()
 *    interprète une ligne vide comme la fin d'une ressource.
 *******************************************************************/
function nettoyerTexte(string $texte, bool $multiligne = false): string
{
    $texte = str_replace('"', '&quot;', $texte);
    $texte = str_replace(["\r\n", "\r"], "\n", $texte); // fins de ligne Windows / Mac -> \n

    if (!$multiligne) {
        return trim((string) preg_replace('/\s+/u', ' ', $texte));
    }

    $lignes = array_map('trim', explode("\n", $texte));
    $lignes = array_filter($lignes, fn($ligne) => $ligne !== '');
    return implode("\n", $lignes);
}


/*******************************************************************
 * Protège une valeur avant de l'afficher dans une page HTML.
 * Le &quot; stocké dans le fichier est d'abord remis en " puis
 * htmlspecialchars() encode tous les caractères spéciaux : on évite
 * ainsi l'affichage de "&amp;quot;" et toute injection HTML/JS.
 *******************************************************************/
function echapper(?string $texte): string
{
    $texte = str_replace('&quot;', '"', $texte ?? '');
    // ENT_SUBSTITUTE : un caractère mal encodé est remplacé au lieu de vider toute la chaîne
    return htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/*******************************************************************
 * Lit et vérifie l'identifiant passé dans l'URL (?id=...).
 * Retourne un entier >= 1, ou null si l'identifiant est absent
 * ou invalide (lettres, nombre négatif, tableau...).
 *******************************************************************/
function lireIdDepuisUrl(): ?int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($id) ? $id : null;
}


/*******************************************************************
 * Vérifie qu'une URL est utilisable comme favori :
 * schéma http ou https, nom d'hôte présent, ni espace ni guillemet.
 * Refuse notamment les liens "javascript:..." (risque XSS).
 *******************************************************************/
function urlValide(string $url): bool
{
    if ($url === '' || preg_match('/[\s"<>]/u', $url)) {
        return false;
    }
    $morceaux = parse_url($url);
    return $morceaux !== false
        && isset($morceaux['scheme'], $morceaux['host'])
        && in_array(strtolower($morceaux['scheme']), ['http', 'https'], true);
}


/*******************************************************************
 * Vérifie un fichier envoyé par formulaire (élément de $_FILES).
 * Retourne null si le fichier est acceptable, sinon le message
 * d'erreur à afficher.
 *******************************************************************/
function verifierFichier(?array $fichier): ?string
{
    // Champ absent ou forgé (tableau de fichiers au lieu d'un seul)
    if ($fichier === null || !isset($fichier['error']) || is_array($fichier['error'])) {
        return "Veuillez choisir un fichier à téléverser.";
    }

    switch ($fichier['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return "Veuillez choisir un fichier à téléverser.";
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "Le fichier dépasse la taille maximale autorisée (" . ini_get('upload_max_filesize') . ").";
        default:
            return "Le téléversement a échoué (code " . $fichier['error'] . "). Veuillez réessayer.";
    }

    // Contrôle de l'extension (liste blanche), sans tenir compte de la casse
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    $listeExtensions = implode(', ', EXTENSIONS_AUTORISEES);

    if ($extension === '') {
        return "Le fichier choisi n'a pas d'extension. Extensions acceptées : $listeExtensions.";
    }
    if (!in_array($extension, EXTENSIONS_AUTORISEES, true)) {
        return "L'extension « .$extension » n'est pas autorisée. Extensions acceptées : $listeExtensions.";
    }
    if ($fichier['size'] === 0) {
        return "Le fichier choisi est vide.";
    }

    return null;
}


/*******************************************************************
 * Enregistre un fichier déjà vérifié dans le dossier uploads/ sous
 * un nom généré côté serveur (jamais le nom d'origine) :
 *   document_AAAAMMJJ-HHMMSS_xxxxxxxx.extension
 * Retourne le nom généré, ou null si le déplacement a échoué.
 *******************************************************************/
function enregistrerFichier(array $fichier): ?string
{
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));

    // Date + 8 caractères aléatoires : nom unique et non devinable
    $nomGenere = 'document_' . date('Ymd-His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    if (!move_uploaded_file($fichier['tmp_name'], DOSSIER_UPLOADS . $nomGenere)) {
        return null;
    }
    return $nomGenere;
}


/*******************************************************************
 * Indique si la requête POST a dépassé post_max_size : dans ce cas
 * PHP vide $_POST et $_FILES, et il faut prévenir l'utilisateur.
 *******************************************************************/
function requeteTropVolumineuse(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST'
        && empty($_POST) && empty($_FILES)
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}


/*******************************************************************
 * Récupère un champ texte envoyé en POST, sans espaces au début et
 * à la fin. Retourne "" si le champ est absent ou n'est pas une
 * chaîne (ex. titre[]=... envoyé par un formulaire forgé).
 * Les octets qui ne sont pas de l'UTF-8 valide sont remplacés par
 * "?" : sinon les fonctions preg_* (drapeau u) renverraient null.
 *******************************************************************/
function lireChampPost(string $nom): string
{
    $valeur = $_POST[$nom] ?? '';
    if (!is_string($valeur)) {
        return '';
    }
    if (!mb_check_encoding($valeur, 'UTF-8')) {
        $valeur = mb_convert_encoding($valeur, 'UTF-8', 'UTF-8');
    }
    return trim($valeur);
}


/*******************************************************************
 * Affiche une date AAAA-MM-JJ au format français JJ/MM/AAAA.
 * Une valeur mal formée est renvoyée telle quelle.
 *******************************************************************/
function formaterDate(string $date): string
{
    $objetDate = DateTime::createFromFormat('Y-m-d', $date);
    return $objetDate ? $objetDate->format('d/m/Y') : $date;
}


/*******************************************************************
 * Taille d'un fichier en octets -> texte lisible ("1,6 Ko").
 *******************************************************************/
function formaterTaille(int $octets): string
{
    if ($octets < 1024) {
        return $octets . ' o';
    }
    if ($octets < 1024 * 1024) {
        return number_format($octets / 1024, 1, ',', ' ') . ' Ko';
    }
    return number_format($octets / (1024 * 1024), 1, ',', ' ') . ' Mo';
}

?>
