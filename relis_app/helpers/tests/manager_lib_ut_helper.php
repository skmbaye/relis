<?php

/*
 * Unit tests for the fix of issue #152.
 *
 * Issue #152: `DynamicList ... depends_on X` was crashing with
 * "Error : Page X not found!" when clicking +Add on the parent category.
 *
 * Root cause: Manager_lib::get_reference_select_values() expects a 
 * "table;column" format (e.g. "ref_brand;ref_value"), but the DSL Forge 
 * generator emits a bare reference name (e.g. "brand") for 
 * DependentDynamicCategory fields.
 *
 * Fix: detect the bare format and transform it to "ref_<name>;ref_value"
 * before calling get_table_configuration().
 *
 * Since get_reference_select_values is private, we test indirectly through
 * the HTTP endpoint Element::add_element_child(), which triggers the
 * function via the +Add modal opening logic.
 *
 * The demoTestProject already contains two DependentDynamicCategory fields
 * (level1 and level2 in the variety super-category) that exercise this 
 * code path.
 */
class ManagerLibUnitTest
{
    private $controller;
    private $http_client;
    private $ci;

    function __construct()
    {
        $this->controller = "element";
        $this->http_client = new Http_client();
        $this->ci = get_instance();
    }

    function run_tests()
    {
        $this->TestInitialize();
        $this->addElementChild_doesNotProducePageNotFoundError();
        $this->addElementChild_returnsHtmlWithDropdownOptions();
    }

    /**
     * Login as admin and switch to the test project.
     */
    private function TestInitialize()
    {
        $this->http_client->response(
            "user",
            "check_form",
            ['user_username' => 'admin', 'user_password' => '123'],
            "POST"
        );
        $this->http_client->response("home", "switch_project/" . getProjectShortName());
    }

    /**
     * Test: opening the +Add modal on a DependentDynamicCategory field must
     * not produce the "Page X not found!" error introduced by the c5a74d0
     * refactoring.
     *
     * Before the fix: the call would crash because Manager_lib received
     *                 a bare name ("brand") and could not find the entity.
     *                 The user would be redirected to home.
     *
     * After the fix:  the modal loads successfully (HTTP < 400, no error
     *                 message in the content).
     */
    private function addElementChild_doesNotProducePageNotFoundError()
    {
        $action      = 'add_element_child';
        $test_name   = 'depends_on does not trigger "Page not found" error';
        $test_aspect = 'Issue #152 - regression check on DependentDynamicCategory';

        // Trigger the endpoint that exercises Manager_lib::get_reference_select_values()
        // via the variety super-category that contains level1/level2 DependentDynamic.
        $response = $this->http_client->response(
            $this->controller,
            $action . "/add_ref_variety/1"
        );

        $expected_value = 'no_page_not_found_error';

        if ($response['status_code'] >= 400) {
            $actual_value = "<span style='color:red'>HTTP " . $response['status_code'] . "</span>";
        } elseif (strpos($response['content'], 'not found!') !== false) {
            $actual_value = "<span style='color:red'>Response contains 'not found!'</span>";
        } else {
            $actual_value = 'no_page_not_found_error';
        }

        run_test(
            $this->controller, $action, $test_name, $test_aspect,
            $expected_value, $actual_value, $response['status_code']
        );
    }

    /**
     * Test: the rendered form must contain dropdown options for the
     * DependentDynamicCategory fields, proving that the values resolution
     * worked end-to-end.
     */
    private function addElementChild_returnsHtmlWithDropdownOptions()
    {
        $action      = 'add_element_child';
        $test_name   = 'depends_on returns a form with dropdown options';
        $test_aspect = 'Issue #152 - end-to-end value resolution';

        $response = $this->http_client->response(
            $this->controller,
            $action . "/add_ref_variety/1"
        );

        $expected_value = 'has_option_tags';

        if ($response['status_code'] >= 400) {
            $actual_value = "<span style='color:red'>HTTP " . $response['status_code'] . "</span>";
        } elseif (strpos($response['content'], '<option') === false) {
            $actual_value = '<span style="color:red">No <option> tag found in response</span>';
        } else {
            $actual_value = 'has_option_tags';
        }

        run_test(
            $this->controller, $action, $test_name, $test_aspect,
            $expected_value, $actual_value, $response['status_code']
        );
    }
}