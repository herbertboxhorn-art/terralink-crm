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
const EQUIP_STATUS = ['active' => 'Active', 'spare' => 'Spare', 'faulty' => 'Faulty', 'retired' => 'Retired'];

const TICKET_PRIORITY = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
const TICKET_STATUS = ['open' => 'Open', 'in_progress' => 'In progress', 'waiting' => 'Waiting on client', 'closed' => 'Closed'];

const QUOTE_STATUS = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired'];
const ITEM_KINDS = ['labour' => 'Labour', 'hardware' => 'Hardware', 'software' => 'Software / licence', 'travel' => 'Travel', 'other' => 'Other'];

const ACTIVITY_TYPES = ['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'visit' => 'Site visit'];

function opts(array $list): array { return array_is_list($list) ? array_combine($list, $list) : $list; }
