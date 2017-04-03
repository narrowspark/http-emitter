# narrowspark contributing guidelines

Thank you for wanting to contribute to narrowspark!

You can find below our guidelines for contribution, explaining how to send [pull requests](#pull-requests), [report issues](#filling-bugs) and [ask questions](#asking-questions), as well as which [workflow](#workflow) we're using while developing narrowspark.

## Maintainers

Current maintainers of narrowspark are:

- [Daniel Bannert](https://github.com/prisis),

If you'll have any questions, feel free to mention us or use emails from our profiles to contact us.


## How you can help

You're welcomed to:

- send pull requests;
- report bugs;
- ask questions;
- fix existing issues;
- suggest new features and enhancements;
- write, rewrite, fix and enhance docs;
- contribute in other ways if you'd like.


### Pull-requests

If you fixed or added something useful to the project, you can send a pull-request. It will be reviewed by a maintainer and accepted, or commented for rework, or declined.

#### Before submitting a PR:

1. Make sure you have tests for your modifications.
2. Run phpunit test locally to catch any errors.
3. It should follow [PSR-2 Coding Standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)** - Check the code style with ``$ vendor/bin/php-cs-fixer fix --config-file=.php_cs -v --diff --dry-run`` and fix it with ``$ vendor/bin/php-cs-fixer fix --config-file=.php_cs -v``.

#### Why did you close my pull request or issue?

Nothing is worse than a project with hundreds of stale issues. To keep things orderly, the maintainers try to close/resolve issues as quickly as possible.

#### PR/Issue closing criteria

We'll close your PR or issue if:

1. It's a duplicate of an existing issue.
2. Outside of the scope of the project.
3. The bug is not reproducible.
4. You are unresponsive after a few days.
5. The feature request introduces too much complexity (or too many edge cases) to the tool
    - We weigh a request's complexity with the value it brings to the community.

Please do not take offense if your ticket is closed. We're only trying to keep the number of issues manageable.

### Filling bugs

If you found an error, typo, or any other flaw in the project, please report it using [GitHub Issues](https://github.com/narrowspark/framework/issues). Try searching the issues to see if there is an existing report of your bug, and if you'd find it, you could bump it by adding your test case there.

When it comes to bugs, the more details you provide, the easier it is to reproduce the issue and the faster it could be fixed.

The best case would be if you'd provide a minimal reproducible test case illustrating a bug. For most cases just a code snippet would be enough, for more complex cases you can create gists or even test repos on GitHub — we would be glad to look into any problems you'll have with narrowspark.

### Asking questions

GitHub issues is not the best place for asking questions like “why my code won't work” or “is there a way to do X in narrowspark”, but we are constantly monitoring the [narrowspark tag at StackOverflow](http://stackoverflow.com/unanswered/tagged/narrowspark), so feel free to ask there! It would make it easier for other people to get answers and to keep GitHub Issues for bugs and feature requests.

### Fixing existing issues

If you'd like to work on an existing issue, just leave a comment on the issue saying that you'll work on a PR fixing it.

### Proposing features

If you've got an idea for a new feature, file an issue providing some details on your idea. Try searching the issues to see if there is an existing proposal for your feature and feel free to bump it by providing your use case or explaining why this feature is important for you.

We should note that not everything should be done as a “narrowspark feature”, some features better be a narrowspark plug-ins, some are just not in the scope of the project.

* * *

## Workflow

This section describes the workflow we use for narrowspark releases, the naming of the branches and the meaning behind them.

### Branches

#### Permanent branches

The following branches should always be there. Do not fork them directly, always create a new branch for your Pull Requests.

- `master`. The code in this branch should always be equal to the latest version that was published in packagist.

- `develop`. This is a branch for coldfixes — both code and documentation. When you're fixing something, it would make sense to send a PR to this branch and not to the `master` — this would make our job a bit easier.

    The code in this branch should always be backwards compatible with `master` — it should only introduce fixes, changes to documentation and other similar things like those, so at every given moment we could create a patch release from it.

#### Temporarily branches

- `issue-NNN`. If you're working on a fix for an issue, you can use this naming. This would make it easy to understand which issue is affected by your code. You can optionally include a postfix with a short description of the problem, for example `issue-1289-broken-mqs`.

- `feature-…`. Any new feature should be initially be a feature-branch. Such branches won't be merged into `master` or `dev` branches directly. The naming would work basically the same as the `issue-…`, but you can omit the issue's number as there couldn't be one issue covering the feature, or you're working on some refactoring.

- `rc-…`. Any new feature release should be at first compiled into a release candidate branch. For example, `rc-0.43` would be a branch for a coming `0.43.0` release. We would merge feature branches and Pull Requests that add new features to the rc-branch, then we test all the changes together, writing tests and docs for those new features and when everything is ready, we increase the version number, then merge the rc-branch into `dev` and `master`.

### Releasing workflow

We follow [semver](http://semver.org/). We're in `0.x` at the moment, however, as narrowspark is already widely used, we don't introduce backwards-incompatible changes to our minor releases.

Each minor release should be first compiled into `rc-`branch. Minor release *should not* have fixes in it, as patch-release should be published before a minor one if there are fixes. This would deliver the fixes to the people using the fixed minor, but `x` at patch version.

Patch releases don't need their own `rc` branches, as they could be released from the `develop` branch.

* * *

This document is inspired my many other Contributing.md files.

**Happy coding**!
