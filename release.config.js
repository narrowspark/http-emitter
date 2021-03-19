module.exports = {
    "branches": [
        "+([0-9])?(.{+([0-9]),x}).x",
        "main",
        "next",
        "next-major",
        {
            "name": "beta",
            "prerelease": true
        },
        {
            "name": "alpha",
            "prerelease": true
        }
    ],
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
                        "from": "\"dev-main\": \".*\"",
                        "to": "\"dev-main\": \"${nextRelease.version.replace(/\\.\\w+$/, '-dev')}\""
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
        [
            "@semantic-release/github",
            {
                "assets": [
                    "docs/**",
                    "src/**",
                    "CHANGELOG.md",
                    "composer.json",
                    "composer.lock",
                    "LICENSE.md",
                    "README.md",
                    "UPGRADE.md"
                ],
                "message": "chore(release): ${nextRelease.version} [skip ci]\n\n${nextRelease.notes}"
            }
        ]
    ]
}
