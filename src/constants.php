<?php
// Option lists — must match the ENUMs in sql/001_schema.sql

const INDUSTRIES = ['Logging & Forestry','Construction & Excavation','Service Companies','Municipalities',
    'Law Firms','First Nations & Band Offices','Clinics & Health Offices','Camps & Lodges','Other'];

const SERVICES = ['AI Automation','Business IT','Business Phones','Hardware & Networks','Starlink & Remote Sites','Other'];

const STAGES = ['new' => 'New', 'audit_booked' => 'Audit booked', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost'];

const SOURCES = ['website' => 'Website', 'referral' => 'Referral', 'cold' => 'Cold outreach', 'repeat' => 'Repeat client',
    'event' => 'Event / trade show', 'other' => 'Other'];

const ROLES = ['admin' => 'Admin', 'sales' => 'Sales', 'tech' => 'Tech'];

const CONNECTIVITY = ['Starlink','Fibre','Cable','DSL','Cellular','Satellite (other)','None','Other'];

const EQUIP_CATEGORIES = ['UniFi','Starlink kit','Phone line','VoIP handset','Server','Workstation','Printer','UPS / Power','Other'];
const EQUIP_STATUS = ['in_stock' => 'In stock', 'active' => 'Installed', 'spare' => 'Spare (on site)', 'faulty' => 'Faulty', 'retired' => 'Retired'];
const STOCK_STATUS = ['in_stock' => 'In stock', 'faulty' => 'Faulty', 'retired' => 'Retired'];
const SITE_STATUS = ['active' => 'Installed', 'spare' => 'Spare (on site)', 'faulty' => 'Faulty', 'retired' => 'Retired'];

const CONSUMABLE_CATEGORIES = ['Cable', 'Connectors', 'Mounting', 'Power', 'Fasteners', 'Tools & supplies', 'Other'];
const CONSUMABLE_UNITS = ['ea' => 'each', 'ft' => 'feet', 'm' => 'metres', 'box' => 'boxes', 'roll' => 'rolls', 'pack' => 'packs'];
const MOVE_REASONS = ['received' => 'Received', 'used' => 'Used', 'adjust' => 'Count adjustment'];

/** Stock locations from Settings (one per line), e.g. Shop / Truck 1 */
function stock_locations(): array {
    $l = array_values(array_filter(array_map('trim', preg_split('/\R|,/', setting('stock_locations', 'Shop')))));
    return $l ?: ['Shop'];
}

const TICKET_PRIORITY = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
const TICKET_STATUS = ['open' => 'Open', 'in_progress' => 'In progress', 'waiting' => 'Waiting on client', 'closed' => 'Closed'];

const QUOTE_STATUS = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired'];
const ITEM_KINDS = ['labour' => 'Labour', 'hardware' => 'Hardware', 'software' => 'Software / licence', 'travel' => 'Travel', 'other' => 'Other'];

const ACTIVITY_TYPES = ['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'visit' => 'Site visit'];

function opts(array $list): array { return array_is_list($list) ? array_combine($list, $list) : $list; }
