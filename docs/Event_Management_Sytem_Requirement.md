**ICT FOUNDATION NEPAL**

ictfoundation.org.np

**Event Management System**

Requirements Document

Version 1.0  |  2026

Prepared by: ICT Foundation Nepal

# **1\. Current Manual Workflow (Existing Process)**

 

*This section documents the existing event data management process currently followed by ICT Foundation Nepal, including coordination with the outsourced event management system team (Technorio).*

 

## **1.1  Data Collection and Database Preparation**

All participant and organizational data are collected and maintained in Excel sheets. The Excel file normally includes the following information for each participant and organization:

•       Organization name

•       Representative name

•       Designation

•       Email address

•       Phone number

•       Website

•       Address

•       Any other required event-specific details

 

Once the Excel sheet is finalized internally, it is shared with the outsourced event management system team (Technorio)  for further processing.

 

## **1.2  System Upload and Communication**

Upon receiving the Excel file, the outsourced team carries out the following tasks:

 

•       Uploads the Excel data into their event management system

•       Receives instructions from IFN on:

◦       When to send bulk email invitations

◦       When to send bulk SMS notifications

◦       Which selected individuals should receive communication

 

## **1.3  QR Code Generation and Invitation Process**

The system generates a unique QR code for each registered individual. The QR code serves as the guest's entry pass for the event:

 

•       Each QR code is unique to the registered individual

•       The QR code is embedded directly in the email invitation

•       Guests present their QR code for entry at the venue

•       At the venue, the QR code is scanned by the event team for verification and entry

 

## **1.4  System Reporting and Error Management**

The outsourced system provides the following reporting and error management functions:

 

| Report / Function | Description |
| ----- | ----- |
| Duplicate Entry Detection | Identifies and flags duplicate registrations in the uploaded data |
| Data Error Identification | Highlights invalid or incomplete records for correction |
| Email Delivery Report | Tracks number of emails sent and delivery status |
| SMS Delivery Report | Tracks number of SMS sent and delivery status |

 

## **1.5  On-site Event Verification**

During the event, the following verification processes are followed:

 

**Guest Entry Verification:**

•       The outsourced team scans the QR code of each guest for entry verification

•       If a guest does not have the QR code, their name is manually searched in the system for verification

 

**Meal (Lunch and Dinner) Validation:**

•       QR codes are scanned to validate food coupons

•       The system enforces a one-time use policy per meal (lunch and dinner)

•       The scanner alerts the operator if someone attempts to use the same meal coupon more than once

 

## **1.6  Post-Event Data Handover**

After the conclusion of the event, the outsourced team provides ICT Foundation Nepal with a set of Excel reports containing the following data:

 

•       Full list of attendees

•       Entry time records per attendee

•       Duplicate entry logs

•       Lunch and dinner usage details

•       Overall event participation data

 

Additionally, IFN currently maintains multiple Excel files stored across different drives. Over time, this has made data retrieval, tracking, and referencing of past event records difficult and inefficient. This is a core limitation the new system must resolve.

 

# **2\. Requirements for the New Centralized Event Management System**

 

*The new system must address all identified limitations of the current process and function as a centralized office management system for all event and guest data across ICT Foundation Nepal.*

 

## **2.1  Centralized Database and Archive**

The system must provide a unified, persistent database for all event-related data:

 

•       Store and archive all historical registration and pre-event invitation data

•       Support searching and filtering across the entire archive by:

◦       Event name

◦       Year

◦       Organization

◦       Individual name

•       Archived data must be reusable and easily migratable for future event cycles

•       Provide a single source of truth, eliminating reliance on scattered Excel sheets

 

## **2.2  Invitation and Registration Archive**

The system must maintain a comprehensive digital archive covering all communication and registration activity:

 

| Archive Component | Details |
| ----- | ----- |
| Invitations Sent | Records of all outgoing invitations with timestamps, recipient lists, and delivery status |
| Registration Data | Complete registration details per event, including participant and organization data |
| Attendance Records | Attendance confirmation, entry time, and no-show tracking per event |
| Communication History | Full log of all email and SMS communications sent per event and per individual |

 

All data must remain accessible within a single integrated system, without the need to cross-reference external files or third-party platforms.

 

## **2.3  Guest and Event Management**

The system must provide an end-to-end event management workflow covering the following functions:

 

•       Current event registration management

•       Guest and organizational data management

•       Unique QR code generation for each registered individual

•       On-site QR code scanning and entry verification

•       Meal (lunch and dinner) coupon validation with one-time use enforcement

•       Meal usage tracking and reporting

 

## **2.4  Office-Level Data Management**

Beyond individual events, the system must serve as the central organizational database for ICT Foundation Nepal:

 

•       Maintain a unified repository of all event records across years

•       Store all guest and organizational data with full history

•       Retain complete communication history across events

•       Track attendance history per individual and per organization

•       Eliminate the need for external Excel coordination and manual data handover

•       Enable authorized internal staff to access, search, and manage data without dependency on third parties

 

## **Summary: Current Limitations vs. New System Requirements**

 

| Current Limitation | New System Requirement |
| ----- | ----- |
| Manual Excel data collection and maintenance | Centralized digital database with structured input |
| Dependency on outsourced system for uploads and communications | Fully in-house system with direct control over all functions |
| No searchable historical archive of past events | Searchable archive by event, year, organization, or individual |
| Multiple scattered Excel files across drives | Single centralized repository for all data |
| Manual data handover post-event | Automatic post-event report generation within the system |
| No unified communication history | Full communication log (email/SMS) stored per event and individual |
| Limited control over on-site verification | Built-in QR scanning and meal validation with real-time alerts |

 

 

*— End of Document —*

[image1]: <data:image/png;base64,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>