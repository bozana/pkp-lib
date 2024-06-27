<?php

namespace PKP\scheduledTask;

use PKP\db\DAORegistry;
use APP\core\Application;
use PKP\task\DepositDois;
use PKP\task\UpdateIPGeoDB;
use PKP\task\ReviewReminder;
use PKP\task\ProcessQueueJobs;
use PKP\task\RemoveFailedJobs;
use PKP\task\StatisticsReport;
use PKP\plugins\PluginRegistry;
use PKP\task\EditorialReminders;
use PKP\scheduledTask\ScheduledTask;
use PKP\task\RemoveExpiredInvitations;
use PKP\scheduledTask\ScheduledTaskDAO;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PKP\task\RemoveUnvalidatedExpiredUsers;
use PKP\plugins\interfaces\HasTaskScheduler;

abstract class PKPScheduler
{
    protected Schedule $schedule;

    protected string $appName;

    protected ScheduledTaskDAO $scheduledTaskDao;

    public function __construct(Schedule $schedule, string $appName = null)
    {
        $this->schedule = $schedule;
        $this->appName = $appName ?? Application::get()->getName();
        $this->scheduledTaskDao = DAORegistry::getDAO('ScheduledTaskDAO');
    }

    public function addSchedule(ScheduledTask $scheduleTask): Event
    {
        $events = $this->schedule->events();

        $scheduleTasks = collect($events)->flatMap(
            fn (Event $event) => [$event->getSummaryForDisplay() => $event]
        );

        $scheduleTaskClass = get_class($scheduleTask);

        // Here we don't want to re-register the schedule task if it's already registered
        // ohterwise it will be same task running multiple time at a given time
        return $scheduleTasks[$scheduleTaskClass]
            ?? $this->schedule->call(fn () => $scheduleTask->execute());
    }

    public function registerSchedules(): void
    {
        $this
            ->schedule
            ->call(fn () => (new ReviewReminder)->execute())
            ->hourly()
            ->name(ReviewReminder::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(ReviewReminder::class));
        
        $this
            ->schedule
            ->call(fn () => (new StatisticsReport)->execute())
            ->daily()
            ->name(StatisticsReport::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(StatisticsReport::class));
        
        $this
            ->schedule
            ->call(fn () => (new DepositDois)->execute())
            ->hourly()
            ->name(DepositDois::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(DepositDois::class));
            
        $this
            ->schedule
            ->call(fn () => (new RemoveUnvalidatedExpiredUsers)->execute())
            ->daily()
            ->name(RemoveUnvalidatedExpiredUsers::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(RemoveUnvalidatedExpiredUsers::class));
        
        $this
            ->schedule
            ->call(fn () => (new EditorialReminders)->execute())
            ->daily()
            ->name(EditorialReminders::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(EditorialReminders::class));
        
        $this
            ->schedule
            ->call(fn () => (new UpdateIPGeoDB)->execute())
            ->cron('0 0 1-10 * *')
            ->name(UpdateIPGeoDB::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(UpdateIPGeoDB::class));

        $this
            ->schedule
            ->call(fn () => (new ProcessQueueJobs)->execute())
            ->everyMinute()
            ->name(ProcessQueueJobs::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(ProcessQueueJobs::class));

        $this
            ->schedule
            ->call(fn () => (new RemoveFailedJobs)->execute())
            ->daily()
            ->name(RemoveFailedJobs::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(RemoveFailedJobs::class));
        
        $this
            ->schedule
            ->call(fn () => (new RemoveExpiredInvitations)->execute())
            ->daily()
            ->name(RemoveExpiredInvitations::class)
            ->withoutOverlapping()
            ->then(fn () => $this->scheduledTaskDao->updateLastRunTime(RemoveExpiredInvitations::class));
        
        $this->registerPluginSchedules();
    }

    public function registerPluginSchedules(): void
    {
        // We only want to load all plugins and register schedule in following way if running on CLI mode
        // as in non cli mode, schedule tasks should be registered from the plugin's `register` method
        // otherwise it will be just double try to register which is memory and time consuming.
        if (!runOnCLI()) {
            return;
        }

        $plugins = PluginRegistry::loadAllPlugins();

        foreach ($plugins as $name => $plugin) {
            if (!$plugin instanceof HasTaskScheduler) {
                continue;
            }

            $plugin->registerSchedules($this);
        }
    }
}
