Approval Bot
============

A basic script used to automatically re-approve Pull Requests when the review is discarded because of new commits or a force-push.

It looks for PR eligible for re-approval
If it finds that
- I have approved them in the past
- I have not approved the latest commit
Then it automatically approves it.

# Usage

Put a GitHub token in file `token.txt`, then run `$ php run.php`

# Limitation

Currenty struggling because GitHub modifies the status of dismissed view requests to "DISMISSED" and I cannot retrieve whether it was an approval, a comment or requested changes.