import myAxios from "../../utils/my-axios";
import React from 'react';
import { states } from '../../utils/states';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { revertAutoSyncStatus } from '../../utils/methods'

export const handleAddDomainToDesec = async function () {
    const { data } = await myAxios.post(
        `${this.props.baseUrl}/api/register-domain`,
        [...this.state.selectedDomains],
    );
};


export const handleDNSRecordsSync = async function () {
    try {
        const { data } = await myAxios.post(
            `${this.props.baseUrl}/api/sync-dns-zone`,
            [...this.state.selectedDomains]
        );

    } catch(error) {
        const key = Math.random().toString();
        console.error(error);
        this.setState(prevState => ({
            toasts: [
                ...prevState.toasts,
                {
                    key,
                    intent: 'danger',
                    message: error.message
                }
            ]
        }));
    }
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
        const key = Math.random().toString();
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
