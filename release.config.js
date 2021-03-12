module.exports = {
    "dryRun": false,
    "plugins": [
        [
            "@semantic-release/commit-analyzer",
            {
                "preset": "conventionalcommits"
            }
        ],
        [
            "@google/semantic-release-replace-plugin",
            {
                "replacements": [
                    {
                        "files": [
                            "composer.json"
                        ],
                        "from": "\"dev-master\": \".*\"",
                        "to": "\"dev-master\": \"${nextRelease.version.replace(/\\.\\w+$/, '-dev')}\"",
                        "results": [
                            {
                                "file": "composer.json",
                                "hasChanged": true,
                                "numMatches": 1,
                                "numReplacements": 1
                            }
                        ],
                        "countMatches": true
                    }
                ]
            }
        ],
        [
            "@semantic-release/release-notes-generator",
            {
                "preset": "conventionalcommits"
            }
        ],
        "@semantic-release/changelog",
        "@semantic-release/github",
        [
            "@semantic-release/git",
            {
                "assets": [
                    "composer.json",
                    "src/*",
                    "UPGRADE.md",
                    "LICENSE.md",
                    "CHANGELOG.md"
                ]
            }
        ]
    ]
}
