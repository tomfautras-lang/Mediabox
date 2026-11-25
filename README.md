# MediaBox – GED en PHP (sans base de données)

Prototype de Gestion Électronique de Documents réalisé pour la société **OrgaPlus**.
MediaBox gère deux sortes de **ressources** affichées dans une même liste :

- les **documents** : fichiers téléversés (PDF, images, sons, vidéos, textes…) ;
- les **favoris** : liens vers des pages web.

Domaine choisi : **automobile / club de passionnés de voitures**. Les membres du club y déposent des
**fiches techniques** (PDF), des **comptes rendus de sorties**, des **photos et affiches**, des
**enregistrements de bruits moteur (MP3)**, des check-lists d'entretien, et y rangent leurs liens utiles
(sites d'essais, annonces d'occasion, démarches administratives, courses).
Les bruits moteur (MP3) s'écoutent et les photos se voient directement dans la liste.

Les métadonnées sont stockées dans le fichier texte `data/ressources.txt` (format `clé:"valeur"`,
une ligne vide entre deux ressources), les fichiers dans le dossier `uploads/`.

---

## Sommaire

1. [Installation](#installation)
2. [Arborescence](#arborescence)
3. [a) Documentation des fonctions fournies](#a-documentation-des-fonctions-fournies)
4. [b) Choix ergonomiques et techniques](#b-choix-ergonomiques-et-techniques)
5. [c) Tests réalisés](#c-tests-réalisés)
6. [d) Usage de l'IA](#d-usage-de-lia)
7. [e) Retour du camarade testeur](#e-retour-du-camarade-testeur)
8. [f) Difficultés rencontrées et solutions](#f-difficultés-rencontrées-et-solutions)

---

## Installation

**Avec XAMPP**

1. Copier le dossier du projet dans `C:\xampp\htdocs\mediabox`.
2. Démarrer **Apache** depuis le panneau de contrôle XAMPP.
3. Ouvrir <http://localhost/mediabox/> dans le navigateur.

**Avec le serveur intégré de PHP** (PHP 8 minimum), depuis le dossier du projet :

```bash
php -S localhost:8000
```

puis ouvrir <http://localhost:8000>.

**Droits d'écriture** : le serveur web doit pouvoir écrire dans `data/ressources.txt` et dans `uploads/`.
Si ce n'est pas le cas, la page d'ajout ou de modification affiche un message
« Enregistrement impossible : le fichier … n'est pas accessible en écriture ».

**Taille des enregistrements** : un MP3 de quelques minutes pèse souvent 3 à 10 Mo. XAMPP autorise 40 Mo par
défaut ; sur une installation de PHP « standard » la limite est de 2 Mo. Il faut alors augmenter
`upload_max_filesize` et `post_max_size` dans `php.ini`. La limite active est affichée sous le champ fichier
et un message clair apparaît si elle est dépassée.

Aucune extension particulière n'est nécessaire : si l'extension `intl` est désactivée
(cas par défaut de XAMPP), `normaliser()` utilise sa solution de repli et le filtre donne
exactement les mêmes résultats (vérifié, voir les tests).

---

## Arborescence

```
index.php            liste des ressources + filtre par mots-clés
ajouter.php          formulaire d'ajout (document ou favori)
modifier.php         formulaire d'édition prérempli
supprimer.php        page de confirmation de suppression
includes/
    fonctions.php    fonctions fournies (lire() modifiée) + fonctions utilitaires ajoutées
data/
    ressources.txt   métadonnées : 17 ressources (8 documents, 9 favoris)
uploads/
    document_1.pdf   fiche technique : le moteur à quatre temps
    document_2.docx  compte rendu : journée de roulage au circuit
    document_3.jpg   illustration : voiture au coucher du soleil
    document_4.mp3   bruit moteur : ralenti (synthèse)
    document_5.mp3   bruit moteur : montée en régime (synthèse)
    document_6.pdf   fiche : 7 contrôles avant un long trajet
    document_7.txt   carnet d'entretien : modèle du club
    document_8.png   affiche : rallye du club
style/
    style.css        feuille de style unique
```

---

## a) Documentation des fonctions fournies

Toutes ces fonctions se trouvent dans `includes/fonctions.php`. Le chemin du fichier de données est
défini par la constante `FICHIER_GED = 'data/ressources.txt'` (chemin relatif : il fonctionne car toutes
les pages sont à la racine du projet).

Une ressource est représentée en PHP par un **tableau associatif** dont les clés sont celles du fichier :

```php
[
    'id'          => '6',              // toujours une chaîne quand elle vient du fichier
    'type'        => 'document',       // 'document' ou 'favori'
    'titre'       => 'Bruit moteur : ralenti et démarrage',
    'mots-cles'   => 'moteur, son, essai, ÉLECTRIQUE',
    'description' => "Première maquette de la composition collective...\npuis mélodie à la main droite.",
    'fichier'     => 'document_4.mp3', // documents uniquement
    'url'         => '...',            // favoris uniquement
    'date'        => '2025-11-06',
]
```

### `lire(array $motsCles = []) : array`  *(modifiée)*

| | |
|---|---|
| **Rôle** | Lit `data/ressources.txt` ligne par ligne et reconstruit la liste des ressources. **Ajout TP** : ne retourne que les ressources correspondant aux mots-clés demandés. |
| **Paramètre** | `$motsCles` : tableau de mots-clés recherchés (ex. `['sport', ' Moteur ']`). Facultatif : tableau vide = pas de filtre. |
| **Retour** | Tableau indexé (0, 1, 2…) de ressources (tableaux associatifs). Tableau vide si le fichier est vide ou si aucune ressource ne correspond. |
| **Effets** | Aucun sur le fichier (lecture seule). Si le fichier n'existe pas, ou si une ligne ne respecte pas la syntaxe `clé:"…`, la fonction **affiche un message d'erreur et arrête le script** (`exit`). |

Fonctionnement de la lecture (code fourni) :

1. `file(..., FILE_IGNORE_NEW_LINES)` charge toutes les lignes dans un tableau.
2. Une ligne vide termine la ressource en cours et l'ajoute au résultat.
3. Sinon, une expression régulière vérifie que la ligne commence par `clé:"` (la clé ne doit contenir
   ni espace, ni `:`, ni `"`). La clé est ce qui précède le premier `:`, la valeur ce qui suit.
4. **Valeur sur plusieurs lignes** : tant que la ligne courante *brute* ne se termine pas par `"`, la ligne
   suivante (si elle n'est pas vide) est ajoutée à la valeur avec un `\n`. Chaque ligne est `trim()`-ée.
5. Les guillemets d'ouverture et de fermeture sont retirés.

Filtre ajouté (fin de la fonction) :

1. Les mots recherchés sont nettoyés par `nettoyerMotsCles()` : `normaliser()` (minuscules, sans accents,
   sans espaces au bord), espaces internes multiples réduits à un seul, mots vides et doublons retirés.
2. S'il ne reste aucun mot, **toutes** les ressources sont retournées.
3. Pour chaque ressource, son champ `mots-cles` est découpé sur les virgules et nettoyé **de la même façon**
   (`extraireMotsCles()`).
4. **Logique ET** : la ressource est gardée seulement si *chaque* mot recherché est présent dans sa liste.
   La comparaison se fait avec `in_array(..., true)` (égalité stricte) : la correspondance est **totale**,
   donc `sport` ne trouve pas `sportive`.

Points d'attention relevés dans le code fourni :

- une **ligne vide à l'intérieur d'une valeur** (paragraphe dans une description) coupe la ressource en deux
  et provoque une « erreur de syntaxe » sur la ligne suivante → l'application supprime les lignes vides
  avant d'enregistrer (voir `nettoyerTexte()`) ;
- le test de fin de valeur porte sur la ligne **non trimée** : un espace après le `"` final (fichier
  édité à la main) fait fusionner la ligne suivante dans la valeur ;
- toutes les valeurs sont des **chaînes** (même `id`).

### `ecrire(array $documents) : void`

| | |
|---|---|
| **Rôle** | Réécrit **entièrement** `data/ressources.txt` à partir d'un tableau de ressources. |
| **Paramètre** | `$documents` : tableau de ressources (tableaux associatifs `clé => valeur`). |
| **Retour** | Rien. Le résultat de `file_put_contents()` n'est pas vérifié. |
| **Effets** | Écrase le fichier. Pour chaque ressource, écrit une ligne `clé:"valeur"` par clé (dans l'ordre du tableau) puis une ligne vide. Les fins de ligne Windows `\r\n` sont converties en `\n`. |

Attention : `ecrire()` **ne remplace pas** les guillemets `"` des valeurs ; c'est à l'appelant de le faire,
sinon le fichier devient illisible. Il n'y a pas non plus de verrou (`LOCK_EX`) : deux enregistrements
simultanés pourraient se marcher dessus (acceptable pour un prototype mono-utilisateur).

### `getDocumentById($id) : ?array`

| | |
|---|---|
| **Rôle** | Cherche une ressource par son identifiant. |
| **Paramètre** | `$id` : identifiant recherché (entier ou chaîne). |
| **Retour** | Le tableau de la ressource trouvée, ou `null` si aucun `id` ne correspond. |
| **Effets** | Aucun (appelle `lire()` sans filtre). |

La comparaison est **non stricte** (`==`) : `"01"` ou `"1.0"` trouveraient la ressource `1`. C'est pourquoi les
pages vérifient d'abord l'identifiant avec `lireIdDepuisUrl()` (entier ≥ 1 uniquement).

### `ajouter(array $document) : int`

| | |
|---|---|
| **Rôle** | Ajoute une nouvelle ressource à la fin du fichier. |
| **Paramètre** | `$document` : tableau associatif **sans** `id` (s'il y en a un, il est écrasé). |
| **Retour** | L'identifiant attribué. |
| **Effets** | Calcule `id = plus grand id existant + 1` (ou `1` si le fichier est vide), place `id` en première clé, ajoute la ressource à la liste puis réécrit tout le fichier avec `ecrire()`. |

Comme l'id vaut « max + 1 » (et non « nombre de ressources + 1 »), un id n'est jamais réutilisé, sauf si
l'on supprime la ressource qui a le plus grand id. Le test `if (isset($document['id']))` est toujours vrai
(l'id vient d'être affecté).

### `modifier($id, array $document) : bool`

| | |
|---|---|
| **Rôle** | Met à jour une ressource existante. |
| **Paramètres** | `$id` : identifiant de la ressource ; `$document` : tableau des champs à modifier. |
| **Retour** | `true` si la ressource a été trouvée et modifiée, `false` sinon. |
| **Effets** | Parcourt les ressources **par référence** (`&$doc`), remplace/ajoute chaque clé fournie (sauf `id`, jamais modifié), remet `id` en première position, puis réécrit le fichier **uniquement** si une modification a eu lieu. |

Une clé absente de `$document` est **conservée** (c'est ce qui permet de garder l'ancien fichier lorsqu'on
n'en choisit pas de nouveau) ; en revanche la fonction ne permet pas de supprimer une clé.

### `supprimer($id) : bool`

| | |
|---|---|
| **Rôle** | Supprime une ressource. |
| **Paramètre** | `$id` : identifiant de la ressource. |
| **Retour** | `true` si une ressource a été supprimée, `false` si l'id n'existe pas. |
| **Effets** | Reconstruit la liste sans la ressource, **supprime aussi le fichier `uploads/<fichier>`** s'il s'agit d'un document et que le fichier existe, puis réécrit le fichier de données. |

### `normaliser(string $texte) : string`

| | |
|---|---|
| **Rôle** | Prépare une chaîne pour une comparaison insensible à la casse, aux accents et aux espaces de bord. |
| **Paramètre** | `$texte` : chaîne à normaliser. |
| **Retour** | La chaîne sans espaces au début/à la fin, en minuscules (`mb_strtolower`, compatible UTF-8), sans accents. Ex. : `"  ÉLECTRO "` → `"electro"`. |
| **Effets** | Aucun. Avec l'extension `intl`, les lettres accentuées sont décomposées (`é` → `e` + accent) puis les accents (`\p{Mn}`) supprimés ; sans `intl`, un tableau de correspondance (`strtr`) est utilisé (`à`→`a`, `ç`→`c`, `œ`→`oe`…). |

### Fonctions utilitaires ajoutées

Elles sont regroupées à la fin de `fonctions.php`, dans une section clairement séparée, pour ne pas
dupliquer le même code dans les quatre pages.

| Fonction | Rôle |
|---|---|
| `normaliserMotCle($mot)` | `normaliser()` + espaces internes multiples réduits à un seul. |
| `nettoyerMotsCles($mots)` | Tableau de mots → mots normalisés, sans vide ni doublon. |
| `extraireMotsCles($liste)` | Chaîne `"a, b, c"` → tableau de mots normalisés. |
| `formaterMotsCles($saisie)` | Met en forme la saisie avant enregistrement : `"  sport ,, Moteur,moteur "` → `"sport, Moteur"`. |
| `nettoyerTexte($texte, $multiligne)` | Remplace `"` par `&quot;`, unifie les fins de ligne, supprime les lignes vides (multiligne) ou les sauts de ligne (une ligne). |
| `echapper($texte)` | Affichage sûr : remet `&quot;` en `"` puis applique `htmlspecialchars()`. |
| `lireIdDepuisUrl()` | Lit `?id=` et retourne un entier ≥ 1, ou `null`. |
| `urlValide($url)` | Accepte seulement `http://` ou `https://` avec un nom d'hôte, sans espace ni guillemet. |
| `verifierFichier($fichier)` | Contrôle un fichier de `$_FILES` (erreur d'envoi, taille, extension autorisée, fichier vide). |
| `enregistrerFichier($fichier)` | Déplace le fichier dans `uploads/` sous un **nom généré**. |
| `requeteTropVolumineuse()` | Détecte un envoi qui dépasse `post_max_size`. |
| `lireChampPost($nom)` | Lit un champ texte POST de façon sûre (absent, tableau, encodage invalide). |
| `formaterDate()`, `formaterTaille()` | Affichage `JJ/MM/AAAA` et taille de fichier lisible. |

---

## b) Choix ergonomiques et techniques

### Format de l'argument de `lire()` : un tableau

`lire(array $motsCles = [])` reçoit un **tableau** de mots-clés. Dans `index.php` :

```php
$ressources = lire(explode(",", $saisie));
```

Justification :

- **Une liste de mots est par nature un tableau** : la signature dit clairement ce qu'attend la fonction.
- **Séparation des rôles** : le découpage de la saisie (virgules) se fait dans la page, `lire()` se charge du
  nettoyage et du filtrage. `lire()` pourrait ainsi être réutilisée avec des mots venant d'ailleurs
  (cases à cocher, mot-clé cliqué…) sans dépendre du séparateur.
- **Valeur par défaut `[]`** : les autres fonctions fournies (`ajouter()`, `modifier()`, `supprimer()`,
  `getDocumentById()`) appellent `lire()` **sans argument** et doivent obtenir **toutes** les ressources
  (sinon `ajouter()` calculerait un mauvais id et `modifier()`/`supprimer()` effaceraient les ressources
  filtrées). Elles continuent de fonctionner sans aucune modification.
- **Robustesse** : `lire()` ne fait pas confiance au tableau reçu : chaque mot est normalisé, les mots vides
  et les doublons sont ignorés. `lire([' ', ''])` se comporte comme `lire()`.

### Bascule du formulaire d'ajout

- Le type est choisi par **deux boutons radio présentés comme de grandes cartes** (« Document » /
  « Favori ») plutôt qu'une liste déroulante : les deux choix sont visibles d'un coup d'œil et sélectionnables
  en un clic.
- Le script (une dizaine de lignes, en bas de `ajouter.php`) masque le bloc inutile avec l'attribut `hidden`
  **et désactive** son champ (`disabled`) : un champ désactivé n'est pas envoyé (pas de fichier téléversé
  inutilement si l'on a changé d'avis).
- La fonction est appelée **au chargement de la page** : quand le formulaire est réaffiché avec des erreurs
  pour un favori, c'est bien le champ URL qui apparaît.
- **Sans JavaScript**, les deux champs restent visibles et le serveur n'utilise que celui qui correspond au
  type choisi : le formulaire reste utilisable.
- Dans `modifier.php`, le type ne peut pas changer : il est affiché sous forme de badge et seul le champ
  utile est généré par PHP (pas besoin de JavaScript). Le type est **toujours relu dans le fichier**, jamais
  dans le formulaire : envoyer `type=document` pour un favori n'a aucun effet (testé).

### Liste des ressources (index.php)

- Affichage en **cartes** (grille responsive) : badge coloré avec icône et texte (📄 *Document* en bleu,
  🔗 *Favori* en orange) et bordure de la même couleur ; le type ne repose donc pas uniquement sur la couleur.
- **Aperçu adapté au thème automobile** : un document MP3 s'écoute directement depuis sa carte (lecteur
  `<audio>`), une photo (jpg, png, gif) est affichée en vignette, une vidéo MP4 dans un lecteur `<video>`.
  Le lecteur audio utilise `preload="none"` : les enregistrementx ne sont téléchargés que si l'on appuie sur
  lecture, la liste reste rapide à charger. Les images ont une largeur et une hauteur (lues avec
  `getimagesize()`) pour éviter que la page « saute » pendant le chargement, et un texte alternatif.
- Pour un document : type et taille du fichier ; pour un favori : nom du site. Si le fichier a disparu du
  serveur ou si l'URL est invalide, un avertissement remplace le bouton « Voir ».
- **Voir** ouvre le fichier ou le site dans un nouvel onglet (`target="_blank"`, avec
  `rel="noopener noreferrer"` pour les sites externes).
- Les **mots-clés sont des liens** : un clic filtre la liste sur ce mot.
- Le filtre utilise la méthode **GET** : le résultat d'un filtre a sa propre URL (bouton Précédent,
  favoris du navigateur). Les mots saisis restent dans le champ, un lien **Réinitialiser** et un bilan
  (« 3 ressources (1 document, 2 favoris) pour « sport » ») sont affichés, ainsi que « **Aucun résultat** »
  si rien ne correspond.
- Après un ajout, une modification ou une suppression, l'utilisateur revient sur la liste avec un message de
  confirmation (codes prévus uniquement : un texte placé dans l'URL n'est jamais affiché).

### Gestion du caractère `"`

Le guillemet droit sert de délimiteur dans le fichier : il est **remplacé par `&quot;`** avant
l'enregistrement (`nettoyerTexte()`), et l'utilisateur en est informé par le message de confirmation.
À l'affichage, `echapper()` remet `&quot;` en `"` **avant** `htmlspecialchars()` : sans cela on verrait
`&amp;quot;` à l'écran, et le formulaire de modification ajouterait un `&amp;` à chaque enregistrement.
Dans une URL, un `"` est refusé avec un message d'erreur (une adresse valide n'en contient pas).

### Téléversement

- Liste blanche : `pdf, jpg, jpeg, png, gif, mp3, mp4, txt, docx, odt` (extension comparée en minuscules :
  `.MP3` est accepté, `.php` refusé). L'attribut `accept` guide aussi l'utilisateur dans la fenêtre de choix.
  C'est la liste imposée par le cahier des charges : les formats audio WAV, FLAC ou OGG sont donc refusés
  (il suffirait de les ajouter à la constante `EXTENSIONS_AUTORISEES` si OrgaPlus le valide).
- **Nom généré côté serveur** : `document_AAAAMMJJ-HHMMSS_xxxxxxxx.ext` (date + 8 caractères aléatoires).
  Ce nom est unique (pas d'écrasement), non devinable, et change à chaque remplacement (le navigateur ne
  réaffiche pas l'ancienne version en cache). Le nom d'origine n'est jamais utilisé.
- En modification, l'**ancien fichier n'est supprimé qu'après** l'enregistrement réussi de la ressource.
- Si une autre erreur bloque le formulaire, un message demande de **resélectionner le fichier**
  (un navigateur ne peut pas pré-remplir un champ fichier).
- Un envoi plus gros que `post_max_size` vide `$_POST` et `$_FILES` : ce cas est détecté et signalé.

### Sécurité et robustesse

- Toute valeur affichée passe par `echapper()` (donc `htmlspecialchars()`), y compris dans les attributs.
- Champs obligatoires vérifiés **côté serveur** (l'attribut `required` n'est qu'un confort).
- Identifiants vérifiés : `?id=abc`, `?id=-3`, `?id=999`, `?id[]=1`… affichent « Ressource introuvable »
  (code HTTP 404) au lieu d'une erreur PHP.
- URL des favoris limitées à `http`/`https` : un lien `javascript:` (injection de script) est refusé.
- Suppression uniquement par formulaire **POST** après confirmation : un simple lien ne peut rien supprimer.
- Champs reçus sous forme de tableau ou mal encodés (non UTF-8) gérés sans erreur.
- Lignes vides retirées des descriptions et sauts de ligne retirés des champs d'une ligne : impossible de
  casser la structure du fichier depuis le formulaire.

Limites connues (acceptables pour un prototype) : pas de jeton anti-CSRF, pas de verrouillage du fichier en
cas d'écritures simultanées, type MIME réel des fichiers non contrôlé (seule l'extension l'est).

### Qualité HTML / CSS

- HTML5 sémantique : `header`, `main`, `footer`, `article`, listes `ul` pour les ressources et les mots-clés,
  `fieldset`/`legend` pour le choix du type, `label` associé à chaque champ, `time` pour les dates.
- Toutes les pages (liste, liste vide de résultat, ajout, ajout avec erreurs, modification d'un document et
  d'un favori, ressource introuvable, suppression) passent le **validateur W3C (Nu HTML Checker)** sans
  erreur ni avertissement.
- Une seule feuille de style externe, organisée en sections numérotées, avec des variables CSS pour les
  couleurs ; mise en page adaptée aux mobiles.

---

## c) Tests réalisés

Tests effectués avec le serveur intégré de PHP 8.2 (XAMPP), dans le navigateur et avec `curl`
(pour envoyer des formulaires volontairement incorrects).

> ⚠️ Les numéros de ressources et résultats attendus de ce chapitre décrivent l'ancien jeu de données (musique) : à refaire avec `data/ressources.txt` actuel avant le rendu.

### Filtre par mots-clés (pièges)

Le jeu de données contient volontairement : `sport` / `sportive` (mot proche), des accents et majuscules
(`Électro`, `electro`, `GUITARE`, `Moteur`), des espaces superflus et une virgule en double
(`"  moteur ,  sport ,, son, essai  "`), un doublon (`moteur, Moteur` dans la même ressource) et une
ressource sans mot-clé (Deezer).

| Saisie dans le filtre | Résultat attendu | Résultat obtenu |
|---|---|---|
| *(vide)* | Toutes les ressources (17) | 17 ✅ |
| `    ` (espaces seuls) | Toutes (équivaut à vide) | 17 ✅ |
| ` , ,, ` (virgules seules) | Toutes | 17 ✅ |
| `sport` | Ressources 7, 8, 11 (pas « sportive ») | 7, 8, 11 ✅ |
| `SPORT` | Idem (casse ignorée) | 7, 8, 11 ✅ |
| `sportive` | 14 | 14 ✅ |
| `sporta` (mot partiel) | Aucun résultat | Aucun résultat ✅ |
| `ÉLECTRO` / `Électro` / `electro` | 4, 11 (« Électro » et « electro ») | 4, 11 ✅ |
| `moteur` / `GUITARE` | 1, 2, 7, 12 | 1, 2, 7, 12 ✅ |
| `circuit, sport` | 9 seulement (logique ET) | 9 ✅ |
| `   circuit   ,    SPORT   ` | 9 (espaces superflus ignorés) | 9 ✅ |
| `circuit,,sport,` | 9 (virgule double et finale ignorées) | 9 ✅ |
| `demo` | 6 et 8 (dont la ressource aux mots-clés mal espacés) | 6, 8 ✅ |
| `démo, démo, DEMO` | 6, 8 (doublons ignorés) | 6, 8 ✅ |
| `sport, classique` | « Aucun résultat » + lien Réinitialiser | ✅ |
| `<script>alert(1)</script>` | Texte affiché sans être exécuté | ✅ |

Ressource sans mot-clé (17) : affichée sans filtre avec la mention « Aucun mot-clé », jamais retenue
par un filtre ✅. Mêmes résultats avec et sans l'extension `intl` ✅.

### Ajout, modification, suppression

| Action | Résultat attendu | Résultat obtenu |
|---|---|---|
| Ajouter un document avec un titre composé d'espaces et sans fichier | « Le titre est obligatoire » + « Veuillez choisir un fichier » | ✅ |
| Ajouter un document `pirate.php` | « L'extension « .php » n'est pas autorisée… », rien n'est enregistré | ✅ |
| Ajouter un favori sans URL / avec `javascript:alert(1)` / avec `www.php.net` | Message d'erreur explicite, saisies conservées | ✅ |
| Ajouter un enregistrement `Ma Chanson.MP3` (espace + majuscules) avec le titre `Ma "première" chanson` | Enregistré, fichier renommé `document_…_xxxxxxxx.mp3`, `"` → `&quot;`, message d'information | ✅ |
| Ajouter une maquette `maquette.wav` | « L'extension « .wav » n'est pas autorisée… » (hors liste blanche) | ✅ |
| Description contenant une ligne vide (`Couplet 1` / *(vide)* / `Refrain`) | Ligne vide retirée, fichier toujours lisible par `lire()` | ✅ |
| Liste après ajout du MP3 | Lecteur audio dans la carte ; les deux démos du jeu de données se lisent (19 s et 20 s) | ✅ |
| Erreur sur un favori (titre vide) | Formulaire réaffiché avec « Favori » coché et le champ URL visible | ✅ |
| Ouvrir la modification d'une ressource contenant des `"` | Formulaire prérempli, guillemets affichés correctement | ✅ |
| Modifier un favori en envoyant `type=document` | Type inchangé (toujours favori) | ✅ |
| Modifier un document sans choisir de fichier | Ancien fichier conservé | ✅ |
| Remplacer le MP3 d'un document par une photo JPG | Nouveau nom enregistré, MP3 supprimé de `uploads/`, vignette affichée | ✅ |
| `modifier.php` / `supprimer.php` avec `id` vide, `abc`, `-3`, `0`, `999`, `1.5`, `id[]=1` | Page « Ressource introuvable » (404), pas d'erreur PHP | ✅ |
| Page de suppression puis **Annuler** | Retour à la liste, rien n'est supprimé | ✅ |
| Appeler `supprimer.php?id=…&confirmation=oui` en GET | Rien n'est supprimé (POST obligatoire) | ✅ |
| **Oui, supprimer** sur un document | Ressource retirée du fichier **et** fichier supprimé de `uploads/` | ✅ |
| Validateur W3C sur les 8 états de pages | 0 erreur, 0 avertissement | ✅ |

---

## d) Usage de l'IA

> ⚠️ **À relire et compléter honnêtement par l'étudiant avant le rendu.**

Ce projet a été réalisé avec l'aide de l'assistant IA **Claude (Anthropic)**, qui a produit le code des
pages, les fonctions utilitaires, la modification de `lire()`, la feuille de style, le jeu de données,
les fichiers d'exemple (les deux démos MP3 ont été synthétisées par programme, les photos dessinées
avec la bibliothèque GD de PHP) et une première version de ce README, à partir de l'énoncé et des
fichiers fournis.

*(Préciser ici ce que vous avez vous-même relu, compris, testé ou modifié.)*

---

## e) Retour du camarade testeur

> ⚠️ **À compléter** : faire tester l'application quelques minutes par un camarade, sans explication.

- **Camarade testeur** : …
- **Ce qui l'a bloqué ou gêné** : …
- **Corrections apportées** : …

---

## f) Difficultés rencontrées et solutions

| Difficulté | Solution |
|---|---|
| Une **ligne vide dans une description** (paragraphes) cassait le fichier : `lire()` la prend pour la fin de la ressource, puis s'arrête sur une « erreur de syntaxe ». | `nettoyerTexte(..., true)` supprime les lignes vides et les espaces en bout de ligne avant l'enregistrement. |
| Les guillemets remplacés par `&quot;` apparaissaient sous la forme `&amp;quot;` après `htmlspecialchars()`, et chaque modification ajoutait un nouveau `&amp;`. | Fonction `echapper()` : retour à `"` puis `htmlspecialchars()` ; l'aller-retour formulaire ↔ fichier reste stable. |
| `ajouter()`, `modifier()` et `supprimer()` appellent `lire()` : ajouter un filtre obligatoire aurait faussé l'id calculé ou supprimé des ressources. | Paramètre **facultatif** avec valeur par défaut `[]`. |
| Mots-clés mal saisis : espaces multiples, virgules en double ou finale, doublons, accents, majuscules. | Même nettoyage des deux côtés (saisie et fichier) avec `nettoyerMotsCles()`, comparaison stricte pour la correspondance totale. |
| L'extension `intl` (classe `Normalizer`) est désactivée par défaut dans XAMPP. | `normaliser()` possède déjà une solution de repli ; les tests du filtre donnent les mêmes résultats avec et sans `intl`. |
| Après une erreur, le fichier choisi est perdu (le navigateur ne peut pas pré-remplir un champ fichier). | Message explicite demandant de resélectionner le fichier ; les autres champs sont conservés. |
| Un fichier trop gros (au-delà de `post_max_size`) vide `$_POST` : le formulaire répondait « type invalide ». | `requeteTropVolumineuse()` détecte ce cas et affiche la taille maximale. |
| Des caractères mal encodés (non UTF-8) faisaient renvoyer `null` aux fonctions `preg_*` : une URL valide était enregistrée vide. | `lireChampPost()` corrige l'encodage dès la lecture du champ ; `htmlspecialchars()` utilise `ENT_SUBSTITUTE`. |
| Un document pouvait être transformé en favori en modifiant le formulaire. | Dans `modifier.php`, le type est lu dans le fichier et jamais dans `$_POST`. |
| `getDocumentById()` compare avec `==` (`"1.0"` trouve l'id 1). | Les identifiants sont validés avec `filter_input(..., FILTER_VALIDATE_INT)` avant tout appel. |
