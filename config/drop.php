<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inscriptions ouvertes
    |--------------------------------------------------------------------------
    |
    | Une instance personnelle peut vouloir fermer la porte une fois ses proches
    | inscrits. Les invitations de groupe restent honorées : un invité crée son
    | compte par le lien reçu même quand l'inscription libre est fermée.
    |
    */

    'registration_open' => (bool) env('DROP_REGISTRATION_OPEN', true),

    /*
    |--------------------------------------------------------------------------
    | Quota par utilisateur
    |--------------------------------------------------------------------------
    |
    | Espace offert à chaque compte, en octets. Les fichiers sont conservés tels
    | qu'ils ont été déposés — aucune compression ne vient jamais « gagner » de
    | la place —, le quota est donc la somme exacte des tailles d'origine.
    | 100 Go par défaut. Le disque du serveur reste la vraie limite : voir
    | disk_reserve_bytes.
    |
    */

    'quota_bytes' => (int) env('DROP_QUOTA_BYTES', 100 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Taille maximale d'un fichier
    |--------------------------------------------------------------------------
    |
    | 50 Go par défaut : une vidéo 4K ProRes d'une heure passe. Le fichier arrive
    | par morceaux, jamais en une seule requête — les limites PHP
    | (upload_max_filesize, post_max_size) ne portent que sur un morceau — et son
    | empreinte se calcule au fil des morceaux : la taille ne coûte rien à la
    | clôture.
    |
    */

    'max_file_bytes' => (int) env('DROP_MAX_FILE_BYTES', 50 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Réserve de disque
    |--------------------------------------------------------------------------
    |
    | Espace à laisser libre sur le disque du serveur, en octets : un dépôt qui
    | l'entamerait est refusé à l'ouverture. La base, les journaux et les aperçus
    | doivent pouvoir continuer d'écrire. 5 Go par défaut.
    |
    */

    'disk_reserve_bytes' => (int) env('DROP_DISK_RESERVE_BYTES', 5 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Taille d'un morceau
    |--------------------------------------------------------------------------
    |
    | Chaque morceau part dans sa propre requête et s'ajoute au fichier en
    | cours. 8 Mo tient largement sous post_max_size (64 Mo dans l'image) tout
    | en limitant le nombre d'allers-retours pour un gros fichier.
    |
    */

    'chunk_bytes' => (int) env('DROP_CHUNK_BYTES', 8 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Dépôts abandonnés
    |--------------------------------------------------------------------------
    |
    | Un dépôt interrompu — onglet fermé, réseau coupé — laisse un fichier
    | partiel. Passé ce délai en heures, il est purgé par le planificateur.
    |
    */

    'stale_upload_hours' => (int) env('DROP_STALE_UPLOAD_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Durée d'un lien à durée limitée
    |--------------------------------------------------------------------------
    |
    | En jours. Un lien sans limite ne périme jamais.
    |
    */

    'share_link_days' => (int) env('DROP_SHARE_LINK_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Miniatures
    |--------------------------------------------------------------------------
    |
    | Largeur des aperçus dérivés. Les aperçus sont les SEULS dérivés produits :
    | le fichier d'origine n'est jamais touché ni réencodé.
    |
    */

    'thumbnail_width' => (int) env('DROP_THUMBNAIL_WIDTH', 640),

    /*
    |--------------------------------------------------------------------------
    | Archivage sans perte
    |--------------------------------------------------------------------------
    |
    | Passé ce nombre de jours sans consultation, une photo est archivée : son
    | original est remplacé sur le disque par une forme compressée réversible —
    | JPEG XL pour les JPEG (~20 % gagnés), zstd pour RAW, TIFF, PNG (10 à
    | 40 %) — dont on reconstruit les octets exacts à la demande. Les vidéos et
    | HEIC, déjà compressés au maximum, ne sont pas touchés. 0 désactive.
    | Modifiable depuis les réglages de l'application.
    |
    | Un fichier n'est archivé que si l'archive fait gagner au moins
    | archive_min_saving (5 %) et si l'original dépasse archive_min_bytes : en
    | dessous, le jeu n'en vaut pas la chandelle.
    |
    */

    'archive_after_days' => (int) env('DROP_ARCHIVE_AFTER_DAYS', 7),
    'archive_min_bytes' => (int) env('DROP_ARCHIVE_MIN_BYTES', 256 * 1024),
    'archive_min_saving' => (float) env('DROP_ARCHIVE_MIN_SAVING', 0.05),
    'archive_batch' => (int) env('DROP_ARCHIVE_BATCH', 200),

    'cjxl' => env('DROP_CJXL', 'cjxl'),
    'djxl' => env('DROP_DJXL', 'djxl'),
    'zstd' => env('DROP_ZSTD', 'zstd'),

    /*
    |--------------------------------------------------------------------------
    | ffmpeg / ffprobe
    |--------------------------------------------------------------------------
    |
    | Chemins des binaires qui lisent les dimensions et la durée des vidéos et
    | en extraient une image d'aperçu. Laissés vides, les vidéos sont acceptées
    | sans aperçu ni métadonnées — jamais refusées.
    |
    */

    'ffprobe' => env('DROP_FFPROBE', 'ffprobe'),
    'ffmpeg' => env('DROP_FFMPEG', 'ffmpeg'),

];
