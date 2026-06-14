@local @local_shula
Feature: Webhook dispatch on module changes
  In order to keep the Shula AI tutor in sync
  As a teacher
  I need actions in Moodle to queue webhooks

  Scenario: Creating a Page activity queues a webhook task
    Given the following "courses" exist:
      | fullname | shortname |
      | Course1  | C1        |
    And a Shula LTI tool exists in course "C1"
    When I create a "Page" activity in course "C1"
    Then the adhoc task queue contains 1 task of type "local_shula\task\send_webhook"