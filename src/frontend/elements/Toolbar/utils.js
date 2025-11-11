import myAxios from "../../utils/my-axios";
import React from 'react';
import { states } from '../../utils/states';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { revertAutoSyncStatus } from '../../utils/methods'

export const handleAddDomainToDesec = async function () {
    // this.setState({ addButtonState: 'loading' });
    // const updatedToasts = [];

    // const updateDomainState = (domains, updateMap, errorMessage= "") =>
    //     domains.map(domain => {
    //         const name = domain["domain-name"];
    //         if (!updateMap[name]) return domain;
    //
    //         updatedToasts.push({
    //             key: Math.random().toString(),
    //             intent: updateMap[name]["desec-status"] === "Registered" ? "success" : "danger",
    //             message:
    //                 updateMap[name]["desec-status"] === "Registered"
    //                     ? `The domain "${name}" was successfully registered with deSEC.`
    //                     : `${errorMessage}`
    //         });
    //
    //         return {
    //             ...domain,
    //             "auto-sync-status": updateMap[name]["desec-status"] === "Registered" ? "true" : "false",
    //             "desec-status": updateMap[name]["desec-status"]
    //         };
    //     });

    // try {
        const { data } = await myAxios.post(
            `${this.props.baseUrl}/api/register-domain`,
            [...this.state.selectedDomains],
        );

        // Map the response into the format expected by updateDomainState
    //     const successMap = {};
    //     for (const name in data) {
    //         successMap[name] = { "desec-status": "Registered" };
    //     }
    //
    //     this.setState(prevState => ({
    //         addButtonState: '',
    //         domains: updateDomainState(prevState.domains, successMap),
    //         toasts: [...prevState.toasts, ...updatedToasts]
    //     }));
    //
    // } catch (error) {
    //     const updateMap = {};
    //     const partialResults = error?.results || {};
    //     const failed = error?.failed_domain;
    //
    //     Object.keys(partialResults).forEach(name => {
    //         updateMap[name] = { "desec-status": "Registered" }
    //     })
    //     if(failed) {
    //         updateMap[failed] = { "desec-status": "Error" }
    //     }
    //
    //     this.setState(prevState => ({
    //         addButtonState: '',
    //         domains: updateDomainState(prevState.domains, updateMap, error.message),
    //         toasts: [...prevState.toasts, ...updatedToasts]
    //     }));
    // }
};


export const handleDNSRecordsSync = async function () {
    const { data } = await myAxios.post(
        `${this.props.baseUrl}/api/sync-dns-zone`,
        [...this.state.selectedDomains]
    );

    console.log(data);
};



export const saveAutoSyncStatus = async function (domainSyncStatus) {

    let payload = {};

    if(Array.isArray(domainSyncStatus[0])) {
        domainSyncStatus.forEach(([ domainId, newStatus ]) => {
            payload[domainId] = newStatus;
        })
    } else {
        const [ domainId, newStatus ] = domainSyncStatus;
        payload[domainId] = newStatus;
    }

    try {
        const {data} = await myAxios.post(
            `${this.props.baseUrl}/api/save-auto-sync-status`,
            payload
        );
        const key = Math.random().toString();

        if(data.success) {
            this.setState(prevState => ({
                toasts: [...prevState.toasts, {
                    key,
                    intent: 'success',
                    message: `Auto-Sync preference was saved successfully!`
                }]
            }));
        } else {
            revertAutoSyncStatus.call(this, domainSyncStatus);
            this.setState(prevState => ({
                toasts: [...prevState.toasts, {
                    key,
                    intent: 'danger',
                    message: `${data.error || 'Unknown error.'}`
                }]
            }));
        }

    } catch (error) {
        revertAutoSyncStatus.call(this, domainSyncStatus);
        this.setState(prevState => ({
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: `An error occurred while saving domain retention status.`
                }
            ]
        }));
    }
};

export const handleSearchChange = function (e) {
    this.setState({searchQuery: e.target.value});
};
