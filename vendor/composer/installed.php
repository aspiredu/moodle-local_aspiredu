<?php return array(
    'root' => array(
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'type' => 'moodle-local',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'reference' => 'bde8d96a3fb05bdf1256d48c2fd4595434622a14',
        'name' => 'aspiredu/moodle-local_aspiredu',
        'dev' => true,
    ),
    'versions' => array(
        'aspiredu/moodle-local_aspiredu' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'type' => 'moodle-local',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'reference' => 'bde8d96a3fb05bdf1256d48c2fd4595434622a14',
            'dev_requirement' => false,
        ),
        'composer/installers' => array(
            'pretty_version' => 'v1.12.0',
            'version' => '1.12.0.0',
            'type' => 'composer-plugin',
            'install_path' => __DIR__ . '/./installers',
            'aliases' => array(),
            'reference' => 'd20a64ed3c94748397ff5973488761b22f6d3f19',
            'dev_requirement' => false,
        ),
        'roundcube/plugin-installer' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'shama/baton' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
    ),
);
