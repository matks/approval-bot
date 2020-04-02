<?php

/**
 * @return int[]
 */
function getPullRequestToCheckIDsFromFile()
{
    $pullRequestsToCheckFileContent = file_get_contents(__DIR__ . '/prs.txt');

    $matches = [];
    $pattern = '#(\d+)#';
    preg_match_all($pattern, $pullRequestsToCheckFileContent, $matches);
    $pullRequestsToCheck = $matches[0];

    return $pullRequestsToCheck;
}

/**
 * @param $pullRequestsToCheck
 * @param $client
 * @param $paginator
 * @param $pullRequestsToApprove
 * @param $pullRequestsAlreadyApproved
 * @param bool $debug
 *
 * @return array
 */
function checkWhetherPullRequestsMustBeReapproved(
    $pullRequestsToCheck,
    $client,
    $paginator,
    $debug)
{
    $pullRequestsToApprove = [];
    $pullRequestsAlreadyApproved = [];
    $pullRequestsWithDismissedReview = [];

    foreach ($pullRequestsToCheck as $pullRequestID) {

        echo "Checking status of PR " . $pullRequestID;

        $pullRequest = $client->api('pull_request')->show('prestashop', 'prestashop', $pullRequestID);

        echo sprintf(
                ' "%s" by @%s',
                $pullRequest['title'],
                $pullRequest['user']['login']
            ) . PHP_EOL;

        $headSha = $pullRequest['head']['sha'];
        if ($debug) {
            echo "PR " . $pullRequestID . ' head sha is ' . $headSha . PHP_EOL;
        }
        $reviewRequestsApi = $client->api('pull_request')->reviews();

        $parameters = ['prestashop', 'prestashop', $pullRequestID];
        $reviewRequests = $paginator->fetchAll($reviewRequestsApi, 'all', $parameters);

        $status = null;

        foreach ($reviewRequests as $reviewRequest) {

            $userLogin = $reviewRequest['user']['login'];
            $reviewStatus = $reviewRequest['state'];
            $reviewedCommitID = $reviewRequest['commit_id'];

            if ('matks' !== $userLogin) {
                continue;
            }

            if ($reviewStatus === 'DISMISSED') {
                if ($status === null) {
                    $status = 'dismissed';
                }
                $pullRequestsWithDismissedReview[] = $pullRequestID;
            }

            if ($reviewStatus !== 'APPROVED') {
                continue;
            }

            if ($reviewedCommitID !== $headSha) {
                if ($debug) {
                    echo 'Matks approved an old commit of PR ' . $pullRequestID . ' (sha: ' . $reviewedCommitID . ')' . PHP_EOL;
                }
                if ($status !== 'already approved') {
                    $status = 'to approve';
                }
                $pullRequestsToApprove[] = $pullRequestID;
            } else {
                if ($debug) {
                    echo 'Matks approved head commit of PR ' . $pullRequestID . PHP_EOL;
                }
                $status = 'already approved';
                $pullRequestsAlreadyApproved[] = $pullRequestID;
            }
        }

        if ($status === null) {
            $status = 'ignored (never approved by matks)';
        }
        if ($status === 'dismissed') {
            $status = sprintf(
                'dismissed ; %s',
                printClickableLink($pullRequest['html_url'], '(click here to see the PR)')
            );
        }

        echo sprintf('- status is %s', $status) . PHP_EOL;
    }

    $pullRequestsAlreadyApproved = array_unique($pullRequestsAlreadyApproved);
    $pullRequestsToApprove = array_unique($pullRequestsToApprove);

    return array($pullRequestsToApprove, $pullRequestsAlreadyApproved);
}

/**
 * @param $migrationPullRequests
 *
 * @return int[]
 */
function parseGitHubDataToExtractPullRequestIDs($migrationPullRequests)
{
    if (empty($migrationPullRequests)) {
        return [];
    }
    if (empty($migrationPullRequests['items'])) {
        return [];
    }

    $pullRequestIds = array_map(function ($pullRequestData) {
        return $pullRequestData['number'];
    }, $migrationPullRequests['items']);

    return $pullRequestIds;
}

function printClickableLink($link, $text)
{
    return "\033]8;;" . $link . "\033\\" . $text . "\033]8;;\033\\";
}
