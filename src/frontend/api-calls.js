import myAxios from "./utils/my-axios";
import axios from 'axios';
import React from 'react';
import { states } from './utils/states';
import { createElement, Component } from '@plesk/plesk-ext-sdk';

export const getDomainsInfo = async function () {
    const key = Math.random().toString();
    this.setState({listLoading: true});

    try {
        const { data } = await myAxios.get(
            `${this.props.baseUrl}/api/get-domains-info`
        );

        console.log(data);

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
            domainError: errorMessage,
            listLoading: false

        }));
    }
};

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
        console.error(error);

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

    const {data} = await axios.get(
        `${this.props.baseUrl}/api/get-domain-retention-status`
    );

    this.setState({
        retainDomainCheck: data["domain-retention"]
    });
};

export const saveLogVerbosityStatus = async function () {
    const key = Math.random().toString();

    const prevValue = this.state.logVerbosityStatus;

    const flippedValue =
        this.state.logVerbosityStatus === "true" ? "false" : "true";

    try {
        const { data } = await axios.post(
            `${this.props.baseUrl}/api/save-log-verbosity-status`,
            [flippedValue]
        );

        if (data.success) {
            this.setState(prevState => ({
                logVerbosityStatus: flippedValue,
                toasts: [
                    ...prevState.toasts,
                    {
                        key,
                        intent: 'success',
                        message: `Log Verbosity status set to ${flippedValue}!`
                    }
                ]
            }));
        }

    } catch (error) {
        console.error(error);

        this.setState(prevState => ({
            logVerbosityStatus: prevValue,
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: `An error occurred while saving the log verbosity status.`
                }
            ]
        }));
    }
};

export const getLogVerbosityStatus = async function () {

    const {data} = await axios.get(
        `${this.props.baseUrl}/api/get-log-verbosity-status`
    );

    this.setState({
        logVerbosityStatus: data["log-verbosity"]
    });
};

export const checkTokenExists = async function () {
    try {
        const { data } = await axios.get(
            `${this.props.baseUrl}/api/retrieve-token`,
        );


        this.setState({
            tokenStatus: data["token"]
        });

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


