Feature: Manage Virtual Machines
    In order to manage my virtual machines
    As an implementing party
    I want to be able to use the Cloudstack API commands

    Scenario: List virtual machines

        Given I have no parameters
        And There is a client instance
        And The expected HTTP method is GET
        And The cloudstack response code is 200
        And The cloudstack response body is:
            """
            [
                {
                    "id": 1,
                    "account" : 4,
                    "displayname": "Test VM"
                }
            ]
            """

        When I execute the listVirtualMachines command

        Then The client should return:
            """
            [
                {
                    "id": 1,
                    "account" : 4,
                    "displayname": "Test VM"
                }
            ]
            """
