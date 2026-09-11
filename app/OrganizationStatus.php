<?php

namespace App;

enum OrganizationStatus: string
{
    case Queued = 'queued';
    case Syncing = 'syncing';
    case Retrying = 'retrying';
    case Complete = 'complete';
    case Failed = 'failed';
}
