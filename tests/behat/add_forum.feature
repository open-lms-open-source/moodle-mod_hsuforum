@mod @mod_hsuforum @_file_upload
Feature: Add Open Forum activities and discussions
  In order to discuss topics with other users
  As a teacher
  I need to add Open Forum activities to moodle courses

  @javascript
  Scenario: Add a Open Forum and a discussion attaching files
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name | Test forum name |
      | Forum type | Standard forum for general use |
      | Description | Test forum description |
    And I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Forum post 1 |
      | Message | This is the body |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I add a new discussion to "Test forum name" Open Forum with:
      | Subject | Post with attachment |
      | Message | This is the body |
      | Attachment | lib/tests/fixtures/empty.txt |
    And I reply "Forum post 1" post from "Test forum name" Open Forum with:
      | Subject | Reply with attachment |
      | Message | This is the body |
      | Attachment | lib/tests/fixtures/upload_users.csv |
    Then I should see "Reply with attachment"
    And I should see "upload_users.csv"
    And I follow "Test forum name"
    And I follow "Post with attachment"
    And I should see "empty.txt"
    And I follow "Edit"
    And I follow "Use advanced editor and additional options"
    And the field "Attachment" matches value "empty.txt"

  @javascript
  Scenario: Display the grade category select according to the grade type options
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Open Forum" to section "1" and I fill the form with:
      | Forum name  | Test forum name                |
      | Forum type  | Standard forum for general use |
      | Description | Test forum description         |
    And I follow "Test forum name"
    And I click on "#region-main-settings-menu .dropdown-toggle>.icon" "css_element"
    And I follow "Edit settings"
    And I click on "#page-mod-hsuforum-mod #id_modstandardratings a[aria-controls='id_modstandardratings']" "css_element"
    And I set the field with xpath "//body[@id='page-mod-hsuforum-mod']//fieldset[@id='id_modstandardratings']//select[@id='id_assessed']" to "Average of ratings"
    And the "disabled" attribute of "#page-mod-hsuforum-mod #id_modstandardgrade > div.fcontainer.clearfix > div:nth-child(3) div[data-fieldtype='select'] select[name='gradecat_hsuforum']" "css_element" should contain "disabled"
    And I click on "#page-mod-hsuforum-mod #id_modstandardgrade a[aria-controls='id_modstandardgrade']" "css_element"
    And I set the field with xpath "//body[@id='page-mod-hsuforum-mod']//fieldset[@id='id_modstandardgrade']//select[@id='id_gradetype']" to "Manual"
    # This final step is only to verify that the user can select something after the Manual option is chosen for grade type.
    And I set the field with xpath "//body[@id='page-mod-hsuforum-mod']//fieldset[@id='id_modstandardgrade']//select[@id='id_gradecat_hsuforum']" to "Uncategorised"
