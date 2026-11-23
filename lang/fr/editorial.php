<?php

return [
    'visuals' => [
        'none' => 'Sans image',
        'original' => 'Image d’origine',
        'blurred' => 'Image floutée automatiquement',
        'edited' => 'Version floutée par la rédaction',
    ],

    'errors' => [
        'not_arbitrable' => 'Ce relais ne peut pas être arbitré dans son état actuel.',
        'reason_required' => 'Indiquez le motif de votre décision.',
        'not_validated' => 'Seul un relais validé peut être sélectionné.',
        'not_selected' => 'Ce relais n’est pas sélectionné.',
        'export_blocked' => 'Ce relais n’est pas validé : son export est bloqué.',
        'derogation_reason_required' => 'Une dérogation exige un motif.',
        'showcase_not_broadcast' => 'Seul un relais diffusé peut être publié sur la vitrine.',
        'showcase_blur' => 'Ce relais demande un floutage : choisissez l’image floutée automatiquement, une version floutée par la rédaction, ou aucune image.',
        'showcase_no_image' => 'Aucune image contrôlée à flouter pour ce relais : publiez sans image ou déposez une version floutée.',
        'showcase_edited_required' => 'Déposez la version floutée (photo ou vidéo).',
        'showcase_edited_type' => 'Version floutée refusée : photo (JPEG, PNG) ou vidéo (MP4, MOV) uniquement.',
        'showcase_edited_infected' => 'Version floutée refusée par l’antivirus.',
        'derogation_forbidden' => 'Seul un administrateur peut accorder une dérogation.',
    ],
];
