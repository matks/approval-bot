Approval Bot
============

A basic script used to automatically re-approve Pull Requests when the review is discarded because of new commits or a force-push.

It looks for PR identifiers in `prs.txt` and checks whether
- I have approved them in the past
- I have not approved the latest commit
Then it automatically approves it.

# Usage

Put a GitHub token in file `token.txt` and create `prs.txt` file, then run `$ php run.php`