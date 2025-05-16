<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel {
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Modules\Grow\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [

            //[growcrm] make sure we have no session during setup
            \Modules\Grow\Http\Middleware\General\Setup::class,

            //system middleware
            \Modules\Grow\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Modules\Grow\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,

            //[GROWNCRM] BOOTING
            \Modules\Grow\Http\Middleware\General\BootSystem::class,
            \Modules\Grow\Http\Middleware\General\BootTheme::class,
            \Modules\Grow\Http\Middleware\General\BootMail::class,

            //[growcrm] [settings middleware]
            \Modules\Grow\Http\Middleware\General\Settings::class,
            //[growcrm] [general middleware]
            \Modules\Grow\Http\Middleware\General\SanityCheck::class,
            //[growcrm] [general middleware]
            \Modules\Grow\Http\Middleware\General\General::class,
            //[growcrm] [modules middleware]
            \Modules\Grow\Http\Middleware\Modules\Status::class,
            //[growcrm] [modules middleware]
            \Modules\Grow\Http\Middleware\Modules\Visibility::class,

            //[MODULES] [growcrm] [modules main menus]
            \Modules\Grow\Http\Middleware\Modules\Bootstrap::class,
            \Modules\Grow\Http\Middleware\Modules\Menus::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [

        //system
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \Modules\Grow\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'memo' => \Modules\Grow\Http\Middleware\General\Memo::class, //[memo]

        /** ---------------------------------------------------------------------------------
         * [SAAS] MIDDLEWARE
         *-----------------------------------------------------------------------------------*/
        'accountStatus' => \Modules\Grow\Http\Middleware\Account\AccountStatus::class,
        'accountLimitsClients' => \Modules\Grow\Http\Middleware\Account\AccountLimitsClients::class,
        'accountLimitsTeam' => \Modules\Grow\Http\Middleware\Account\AccountLimitsTeam::class,
        'accountLimitsProjects' => \Modules\Grow\Http\Middleware\Account\AccountLimitsProjects::class,

        /** ---------------------------------------------------------------------------------
         * CRM MIDDLEWARE
         *-----------------------------------------------------------------------------------*/

        //[growcrm] - [general]
        'adminCheck' => \Modules\Grow\Http\Middleware\General\AdminCheck::class,
        'teamCheck' => \Modules\Grow\Http\Middleware\General\TeamCheck::class,
        'generalMiddleware' => \Modules\Grow\Http\Middleware\General\General::class,
        'demoModeCheck' => \Modules\Grow\Http\Middleware\General\DemoCheck::class,
        'FileSecurityCheck' => \Modules\Grow\Http\Middleware\Fileupload\FileSecurityCheck::class,

        //[growcrm] - [authentication]
        'authenticationMiddlewareGeneral' => \Modules\Grow\Http\Middleware\Authenticate\General::class,

        //[growcrm] - [authentication]
        'categoriesMiddlewareGeneral' => \Modules\Grow\Http\Middleware\Categories\General::class,

        //[growcrm] - [clients]
        'clientsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Clients\Index::class,
        'clientsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Clients\Edit::class,
        'clientsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Clients\Destroy::class,
        'clientsMiddlewareShow' => \Modules\Grow\Http\Middleware\Clients\Show::class,
        'clientsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Clients\Create::class,
        'importClientsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Import\Clients\Create::class,

        //[growcrm] - [projects]
        'projectsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Projects\Index::class,
        'projectsMiddlewareShow' => \Modules\Grow\Http\Middleware\Projects\Show::class,
        'projectsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Projects\Edit::class,
        'projectsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Projects\Create::class,
        'projectsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Projects\Destroy::class,
        'projectsMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Projects\BulkEdit::class,
        'projectsMiddlewareBulkAssign' => \Modules\Grow\Http\Middleware\Projects\BulkAssign::class,

        //[growcrm] - [knowledgebase]
        'knowledgebaseMiddlewareIndex' => \Modules\Grow\Http\Middleware\Knowledgebase\Index::class,
        'knowledgebaseMiddlewareCreate' => \Modules\Grow\Http\Middleware\Knowledgebase\Create::class,
        'knowledgebaseMiddlewareEdit' => \Modules\Grow\Http\Middleware\Knowledgebase\Edit::class,
        'knowledgebaseMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Knowledgebase\Destroy::class,
        'knowledgebaseMiddlewareShow' => \Modules\Grow\Http\Middleware\Knowledgebase\Show::class,

        //[growcrm] - [knowledgebase]
        'knowledgebaseCategoriesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Kbcategories\Edit::class,
        'knowledgebaseCategoriesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Kbcategories\Destroy::class,

        //[growcrm] - [timesheets]
        'timesheetsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Timesheets\Index::class,
        'timesheetsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Timesheets\Destroy::class,
        'timesheetsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Timesheets\Edit::class,

        //[growcrm] - [settings]
        'settingsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Settings\Index::class,

        //[growcrm] - [expenses]
        'expensesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Expenses\Index::class,
        'expensesMiddlewareShow' => \Modules\Grow\Http\Middleware\Expenses\Show::class,
        'expensesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Expenses\Edit::class,
        'expensesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Expenses\Create::class,
        'expensesMiddlewareDownloadAttachment' => \Modules\Grow\Http\Middleware\Expenses\DownloadAttachment::class,
        'expensesMiddlewareDeleteAttachment' => \Modules\Grow\Http\Middleware\Expenses\DeleteAttachment::class,
        'expensesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Expenses\Destroy::class,
        'expensesMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Expenses\BulkEdit::class,
        'expensesMiddlewareGeneralSingleActions' => \Modules\Grow\Http\Middleware\Expenses\GeneralSingleActions::class,
        'expensesMiddlewareCreateInvoice' => \Modules\Grow\Http\Middleware\Expenses\Createinvoice::class,

        //[growcrm] - [invoices]
        'invoicesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Invoices\Index::class,
        'invoicesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Invoices\Create::class,
        'invoicesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Invoices\Edit::class,
        'invoicesMiddlewareShow' => \Modules\Grow\Http\Middleware\Invoices\Show::class,
        'invoicesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Invoices\Destroy::class,
        'invoicesMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Invoices\BulkEdit::class,
        'invoicesMiddlewareGeneralSingleActions' => \Modules\Grow\Http\Middleware\Invoices\GeneralSingleActions::class,

        //[growcrm] - [estimates]
        'estimatesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Estimates\Index::class,
        'estimatesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Estimates\Create::class,
        'estimatesMiddlewareShow' => \Modules\Grow\Http\Middleware\Estimates\Show::class,
        'estimatesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Estimates\Destroy::class,
        'estimatesMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Estimates\BulkEdit::class,
        'estimatesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Estimates\Edit::class,
        'estimatesMiddlewareShowPublic' => \Modules\Grow\Http\Middleware\Estimates\ShowPublic::class,

        //[growcrm] - [payments]
        'paymentsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Payments\Index::class,
        'paymentsMiddlewareShow' => \Modules\Grow\Http\Middleware\Payments\Show::class,
        'paymentsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Payments\Destroy::class,
        'paymentsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Payments\Create::class,
        'paymentsMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Payments\BulkEdit::class, //DONE
        'paymentsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Payments\Edit::class, //DONE

        //[growcrm] - [notes]
        'notesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Notes\Index::class,
        'notesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Notes\Create::class,
        'notesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Notes\Edit::class,
        'notesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Notes\Destroy::class,
        'notesMiddlewareShow' => \Modules\Grow\Http\Middleware\Notes\Show::class,

        //[growcrm] - [items]
        'itemsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Items\Index::class,
        'itemsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Items\Create::class,
        'itemsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Items\Edit::class,
        'itemsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Items\Destroy::class,
        'itemsMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Items\BulkEdit::class, //DONE

        //[growcrm] - [contacts]
        'contactsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Contacts\Index::class,
        'contactsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Contacts\Create::class,
        'contactsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Contacts\Edit::class,
        'contactsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Contacts\Destroy::class,
        'contactsMiddlewareShow' => \Modules\Grow\Http\Middleware\Contacts\Show::class,

        //[growcrm] - [tickets]
        'ticketsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Tickets\Index::class,
        'ticketsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Tickets\Create::class,
        'ticketsMiddlewareShow' => \Modules\Grow\Http\Middleware\Tickets\Show::class,
        'ticketsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Tickets\Edit::class,
        'ticketsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Tickets\Destroy::class,
        'ticketsMiddlewareReply' => \Modules\Grow\Http\Middleware\Tickets\Reply::class,
        'ticketsMiddlewareDownloadAttachment' => \Modules\Grow\Http\Middleware\Tickets\DownloadAttachment::class,
        'ticketsMiddlewareEditReply' => \Modules\Grow\Http\Middleware\Tickets\EditReply::class,
        'ticketsMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Tickets\BulkEdit::class, //DONE

        //[growcrm] - [leads]
        'leadsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Leads\Index::class,
        'leadsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Leads\Create::class,
        'leadsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Leads\Edit::class,
        'leadsMiddlewareShow' => \Modules\Grow\Http\Middleware\Leads\Show::class,
        'leadsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Leads\Destroy::class,
        'leadsMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Leads\BulkEdit::class,
        'leadsMiddlewareParticipate' => \Modules\Grow\Http\Middleware\Leads\Participate::class,
        'leadsMiddlewareDeleteAttachment' => \Modules\Grow\Http\Middleware\Leads\DeleteAttachment::class,
        'leadsMiddlewareDownloadAttachment' => \Modules\Grow\Http\Middleware\Leads\DownloadAttachment::class,
        'leadsMiddlewareDeleteComment' => \Modules\Grow\Http\Middleware\Leads\DeleteComment::class,
        'leadsMiddlewareEditDeleteChecklist' => \Modules\Grow\Http\Middleware\Leads\EditDeleteChecklist::class,
        'leadsMiddlewareAssign' => \Modules\Grow\Http\Middleware\Leads\Assign::class,
        'importLeadsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Import\Leads\Create::class,
        'leadsMiddlewareCloning' => \Modules\Grow\Http\Middleware\Leads\Cloning::class,
        'leadsMiddlewareBulkAssign' => \Modules\Grow\Http\Middleware\Leads\BulkAssign::class,

        //[growcrm] - [tasks]
        'tasksMiddlewareIndex' => \Modules\Grow\Http\Middleware\Tasks\Index::class,
        'tasksMiddlewareShow' => \Modules\Grow\Http\Middleware\Tasks\Show::class,
        'tasksMiddlewareCreate' => \Modules\Grow\Http\Middleware\Tasks\Create::class,
        'tasksMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Tasks\Destroy::class,
        'tasksMiddlewareTimer' => \Modules\Grow\Http\Middleware\Tasks\Timer::class,
        'tasksMiddlewareEdit' => \Modules\Grow\Http\Middleware\Tasks\Edit::class,
        'tasksMiddlewareParticipate' => \Modules\Grow\Http\Middleware\Tasks\Participate::class,
        'tasksMiddlewareDeleteAttachment' => \Modules\Grow\Http\Middleware\Tasks\DeleteAttachment::class,
        'tasksMiddlewareDownloadAttachment' => \Modules\Grow\Http\Middleware\Tasks\DownloadAttachment::class,
        'tasksMiddlewareDeleteComment' => \Modules\Grow\Http\Middleware\Tasks\DeleteComment::class,
        'tasksMiddlewareEditDeleteChecklist' => \Modules\Grow\Http\Middleware\Tasks\EditDeleteChecklist::class,
        'tasksMiddlewareAssign' => \Modules\Grow\Http\Middleware\Tasks\Assign::class,
        'tasksMiddlewareCloning' => \Modules\Grow\Http\Middleware\Tasks\Cloning::class,
        'tasksMiddlewareManageDependencies' => \Modules\Grow\Http\Middleware\Tasks\ManageDependencies::class,

        //[growcrm] - [files]
        'filesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Files\Index::class,
        'filesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Files\Create::class,
        'filesMiddlewareDownload' => \Modules\Grow\Http\Middleware\Files\Download::class,
        'filesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Files\Destroy::class,
        'filesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Files\Edit::class,
        'filesMiddlewareMove' => \Modules\Grow\Http\Middleware\Files\Move::class,
        'filesMiddlewareBulkDownload' => \Modules\Grow\Http\Middleware\Files\BulkDownload::class,
        'manageFoldersMiddleware' => \Modules\Grow\Http\Middleware\Files\ManageFolders::class,
        'filesMiddlewareCopy' => \Modules\Grow\Http\Middleware\Files\Copy::class,

        //[growcrm] - [comments]
        'commentsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Comments\Index::class,
        'commentsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Comments\Create::class,
        'commentsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Comments\Destroy::class,

        //[growcrm] - [milestone]
        'milestonesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Milestones\Index::class,
        'milestonesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Milestones\Create::class,
        'milestonesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Milestones\Edit::class,
        'milestonesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Milestones\Destroy::class,

        //[growcrm] - [subscription]
        'subscriptionsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Subscriptions\Index::class,
        'subscriptionsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Subscriptions\Create::class,
        'subscriptionsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Subscriptions\Edit::class,
        'subscriptionsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Subscriptions\Destroy::class,
        'subscriptionsMiddlewareShow' => \Modules\Grow\Http\Middleware\Subscriptions\Show::class,
        'subscriptionsMiddlewareCancel' => \Modules\Grow\Http\Middleware\Subscriptions\Cancel::class,

        //[growcrm] - [milestone]
        'homeMiddlewareIndex' => \Modules\Grow\Http\Middleware\Home\Index::class,

        //[growcrm] - [project templates]
        'projectTemplatesGeneral' => \Modules\Grow\Http\Middleware\Projects\ProjectTemplatesGeneral::class,
        'projectTemplatesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Templates\Projects\Index::class,
        'projectTemplatesMiddlewareShow' => \Modules\Grow\Http\Middleware\Templates\Projects\Show::class,
        'projectTemplatesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Templates\Projects\Edit::class,
        'projectTemplatesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Templates\Projects\Create::class,
        'projectTemplatesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Templates\Projects\Destroy::class,

        //[growcrm] - [customfields]
        'customfieldsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Settings\CustomFields\Edit::class,

        //[growcrm] - [team]
        'teamMiddlewareIndex' => \Modules\Grow\Http\Middleware\Team\Index::class,
        'teamMiddlewareCreate' => \Modules\Grow\Http\Middleware\Team\Create::class,
        'teamMiddlewareEdit' => \Modules\Grow\Http\Middleware\Team\Edit::class,

        //[growcrm] - [proposals]
        'proposalsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Proposals\Index::class,
        'proposalsMiddlewareShow' => \Modules\Grow\Http\Middleware\Proposals\Show::class,
        'proposalsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Proposals\Create::class,
        'proposalsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Proposals\Edit::class,
        'proposalsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Proposals\Destroy::class,
        'proposalsMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Proposals\BulkEdit::class,
        'proposalsMiddlewareShowPublic' => \Modules\Grow\Http\Middleware\Proposals\ShowPublic::class,

        //[growcrm] - [contracts]
        'contractsMiddlewareIndex' => \Modules\Grow\Http\Middleware\Contracts\Index::class,
        'contractsMiddlewareShow' => \Modules\Grow\Http\Middleware\Contracts\Show::class,
        'contractsMiddlewareCreate' => \Modules\Grow\Http\Middleware\Contracts\Create::class,
        'contractsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Contracts\Edit::class,
        'contractsMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Contracts\Destroy::class,
        'contractsMiddlewareBulkEdit' => \Modules\Grow\Http\Middleware\Contracts\BulkEdit::class,
        'contractsMiddlewareShowPublic' => \Modules\Grow\Http\Middleware\Contracts\ShowPublic::class,
        'contractsMiddlewareSignClient' => \Modules\Grow\Http\Middleware\Contracts\SignClient::class,
        'contractsMiddlewareSignTeam' => \Modules\Grow\Http\Middleware\Contracts\SignTeam::class,

        //[growcrm] - [documents](proposals & contracts)
        'documentsMiddlewareEdit' => \Modules\Grow\Http\Middleware\Documents\Edit::class,

        //[growcrm] - [spaces]
        'spacesMiddlewareShow' => \Modules\Grow\Http\Middleware\Spaces\Show::class,

        //[growcrm] - [product tasks]
        'productTasksMiddlewareView' => \Modules\Grow\Http\Middleware\Items\TasksView::class,
        'productTasksMiddlewareEdit' => \Modules\Grow\Http\Middleware\Items\TasksEdit::class,

        //[growcrm] - [messages]
        'messagesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Messages\Index::class,
        'messagesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Messages\Destroy::class,
        'messagesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Messages\Create::class,

        //[growcrm] - [proposal templates]
        'proposalTemplatesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Templates\Proposals\Index::class,
        'proposalTemplatesMiddlewareShow' => \Modules\Grow\Http\Middleware\Templates\Proposals\Show::class,
        'proposalTemplatesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Templates\Proposals\Edit::class,
        'proposalTemplatesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Templates\Proposals\Create::class,
        'proposalTemplatesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Templates\Proposals\Destroy::class,

        //[growcrm] - [contract templates]
        'contractTemplatesMiddlewareIndex' => \Modules\Grow\Http\Middleware\Templates\Contracts\Index::class,
        'contractTemplatesMiddlewareShow' => \Modules\Grow\Http\Middleware\Templates\Contracts\Show::class,
        'contractTemplatesMiddlewareEdit' => \Modules\Grow\Http\Middleware\Templates\Contracts\Edit::class,
        'contractTemplatesMiddlewareCreate' => \Modules\Grow\Http\Middleware\Templates\Contracts\Create::class,
        'contractTemplatesMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Templates\Contracts\Destroy::class,

        //[growcrm] - [reports]
        'reportsMiddlewareShow' => \Modules\Grow\Http\Middleware\Reports\Show::class,

        //[growcrm] - [contract templates]
        'cannedMiddlewareIndex' => \Modules\Grow\Http\Middleware\Canned\Index::class,
        'cannedMiddlewareShow' => \Modules\Grow\Http\Middleware\Canned\Show::class,
        'cannedMiddlewareEdit' => \Modules\Grow\Http\Middleware\Canned\Edit::class,
        'cannedMiddlewareCreate' => \Modules\Grow\Http\Middleware\Canned\Create::class,
        'cannedMiddlewareDestroy' => \Modules\Grow\Http\Middleware\Canned\Destroy::class,

        //[growcrm] - [reports]
        'searchMiddlewareIndex' => \Modules\Grow\Http\Middleware\Search\Index::class,
    ];
}
