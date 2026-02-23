import myAxios from "./utils/my-axios";
import React from 'react';
import { states } from './utils/states';
import { createElement, Component } from '@plesk/plesk-ext-sdk';

export const getUserEulaDecision = async function() {
    const key = Math.random().toString();

    try {
        const {data} = await myAxios.get(
            `${this.props.baseUrl}/api/get-user-eula-decision`
        );


        this.setState({
            eulaDecision: data["eula-decision"] === "true"
        });

        return data["eula-decision"] === "true"

    } catch (error) {
        const errorMessage = error?.message

        this.setState(prevState => ({
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: `${errorMessage}`
                }
            ],
        }));
    }
}

export const saveUserEulaDecision = async function(decision) {
    const key = Math.random().toString();

    try {
        const { data } = await myAxios.post(
            `${this.props.baseUrl}/api/save-user-eula-decision`,
            [decision]
        );


    } catch (error) {
        const errorMessage = error?.message

        this.setState(prevState => ({
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: `${errorMessage}`
                }
            ],
        }));
    }
}
export const getDomainsInfo = async function () {
    const key = Math.random().toString();

    this.setState({ listLoading: true });

    try {
        const {data} = await myAxios.get(
            `${this.props.baseUrl}/api/get-domains-info`
        );
        const domainsArray = Array.isArray(data)
            ? data
            : Object.values(data);


        this.setState(prevState => ({
            domains: domainsArray,
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'success',
                    message: `Successfully fetched ${domainsArray.length} domain(s).`
                }
            ],

            listLoading: false
        }));

    } catch (error) {
        const errorMessage =
            error?.message || this.state.domainError || "Unknown error occurred";

        this.setState(prevState => ({
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: `${errorMessage}`
                }
            ],
            emptyViewTitle: "Something went wrong!",
            emptyViewDescription: "An error occurred while fetching domains information.",
            listLoading: false

        }));
    }
};

// ================== Domain Retention ==================

export const saveDomainRetentionStatus = async function () {
    const key = Math.random().toString();

    const prevValue = this.state.retainDomainCheck;

    const flippedValue =
        this.state.retainDomainCheck === "true" ? "false" : "true";

    try {
        const { data } = await myAxios.post(
            `${this.props.baseUrl}/api/save-domain-retention-status`,
            [flippedValue]
        );

        if (data.success) {
            this.setState(prevState => ({
                retainDomainCheck: flippedValue,
                toasts: [
                    ...prevState.toasts,
                    {
                        key,
                        intent: 'success',
                        message: `Domain retention status set to ${flippedValue}!`
                    }
                ]
            }));
        }

    } catch (error) {

        this.setState(prevState => ({
            retainDomainCheck: prevValue,
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: `An error occurred while saving domain retention status! ${error.message}`
                }
            ]
        }));
    }
};

export const getDomainRetentionStatus = async function () {
    const key = Math.random().toString();

    try {
        const {data} = await myAxios.get(
            `${this.props.baseUrl}/api/get-domain-retention-status`
        );

        this.setState({
            retainDomainCheck: data["domain-retention"]
        });

    } catch (error) {

        this.setState(prevState => ({
            retainDomainCheck: prevState.retainDomainCheck,
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: `An error occurred while fetching the domain retention status.`
                }
            ]
        }));
    }
};

// ================== deSEC API token ==================

export const checkTokenExists = async function () {
    try {
        const { data } = await myAxios.get(
            `${this.props.baseUrl}/api/retrieve-token`,
        );

        this.setState({
            tokenStatus: data["token"] === "true"
        });


        return data["token"] === "true"

        if(!this.state.tokenStatus) {
            this.setState({
                emptyViewTitle: "Missing credentials!",
                emptyViewDescription:
                    "The deSEC token used within the extension is missing or it was misplaced! Please check the pm_Settings object or panel.ini.",
                isFormOpen: true,
            });
        }

    } catch(error) {
        this.setState(prevState => ({
            toasts: [
                ...prevState.toasts,
                {
                    key: Math.random().toString(),
                    intent: 'danger',
                    message: `${error.message}`
                }
            ]}),
        );
    }
}

export const validateToken = async function () {


    try {
        const { data } = await myAxios.post(
            `${this.props.baseUrl}/api/validate-token`,
                [this.state.inputToken]
        )

        if(data.token === "true") {
            this.setState(prevState => ({
                toasts: [
                    ...prevState.toasts,
                    {
                        key: Math.random().toString(),
                        intent: 'success',
                        message: `Valid deSEC API token provided. Welcome!`
                    }
                ],

                tokenStatus: true,
                }
            ));

        } else {
            this.setState(prevState => ({
                toasts: [
                    ...prevState.toasts,
                    {
                        key: Math.random().toString(),
                        intent: 'danger',
                        message: `Invalid deSEC API token. Please insert a valid one!`
                    }
                ],
                tokenStatus: false,
                })
            );

        }


    } catch(error) {
        this.setState(prevState => ({
            toasts: [
                ...prevState.toasts,
                {
                    key: Math.random().toString(),
                    intent: 'danger',
                    message: `${error.message}`
                }
            ],
            })
        );
    }
}


