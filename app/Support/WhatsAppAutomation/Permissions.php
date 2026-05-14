<?php

namespace App\Support\WhatsAppAutomation;

class Permissions
{
    public const ACCESS = 'whatsapp.access';
    public const DASHBOARD_VIEW = 'whatsapp.dashboard.view';
    public const LEADS_VIEW = 'whatsapp.leads.view';
    public const LEADS_UPDATE = 'whatsapp.leads.update';
    public const TAGS_MANAGE = 'whatsapp.tags.manage';
    public const FOLLOWUPS_VIEW = 'whatsapp.followups.view';
    public const FOLLOWUPS_MANAGE = 'whatsapp.followups.manage';
    public const HANDOFF_VIEW = 'whatsapp.handoff.view';
    public const HANDOFF_RESOLVE = 'whatsapp.handoff.resolve';
    public const CAMPAIGN_VIEW = 'whatsapp.campaign.view';
    public const CAMPAIGN_MANAGE = 'whatsapp.campaign.manage';
    public const OUTBOUND_VIEW = 'whatsapp.outbound.view';
    public const SCHEDULER_RUN = 'whatsapp.scheduler.run';

    public static function all(): array
    {
        return [
            self::ACCESS,
            self::DASHBOARD_VIEW,
            self::LEADS_VIEW,
            self::LEADS_UPDATE,
            self::TAGS_MANAGE,
            self::FOLLOWUPS_VIEW,
            self::FOLLOWUPS_MANAGE,
            self::HANDOFF_VIEW,
            self::HANDOFF_RESOLVE,
            self::CAMPAIGN_VIEW,
            self::CAMPAIGN_MANAGE,
            self::OUTBOUND_VIEW,
            self::SCHEDULER_RUN,
        ];
    }
}