import myAxios from "../../utils/my-axios";
import React from 'react';
import { states } from '../../utils/states';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { revertAutoSyncStatus } from '../../utils/methods'

export const handleAddDomainToDesec = async function () {
    this.setState({ addButtonState: 'loading' });
    const updatedToasts = [];

    const updateDomainState = (domains, updateMap, errorMessage= "") =>
        domains.map(domain => {
            const name = domain["domain-name"];
            if (!updateMap[name]) return domain;

            updatedToasts.push({
                key: Math.random().toString(),
                intent: updateMap[name]["desec-status"] === "Registered" ? "success" : "danger",
                message:
                    updateMap[name]["desec-status"] === "Registered"
                        ? `The domain "${name}" was successfully registered with deSEC.`
                        : `${errorMessage}`
            });

            return {
                ...domain,
                "auto-sync-status": updateMap[name]["desec-status"] === "Registered" ? "true" : "false",
                "desec-status": updateMap[name]["desec-status"]
            };
        });

    try {
        const { data } = await myAxios.post(
            `${this.props.baseUrl}/api/register-domain`,
            [...this.state.selectedDomains],
            { validateStatus: () => true }
        );

        // Map the response into the format expected by updateDomainState
        const successMap = {};
        for (const name in data) {
            successMap[name] = { "desec-status": "Registered" };
        }

        this.setState(prevState => ({
            addButtonState: '',
            domains: updateDomainState(prevState.domains, successMap),
            toasts: [...prevState.toasts, ...updatedToasts]
        }));

    } catch (error) {
        const failedDomains = {};
        const errorResults = error?.results || {};
        const failed = error?.failed_domain;

        if (failed) {
            failedDomains[failed] = { "desec-status": "Error" };
        }

        this.setState(prevState => ({
            addButtonState: '',
            domains: updateDomainState(prevState.domains, failedDomains, error.message),
            toasts: [...prevState.toasts, ...updatedToasts]
        }));
    }
};


export const handleDNSRecordsSync = async function () {
    this.setState({ syncButtonState: "loading" });

    const formatToastMessage = (domainName, timestamp, missing, modified, deleted) => (
        <div style={{ whiteSpace: 'pre-line' }}>
            <strong>{domainName} sync completed at {timestamp}</strong>
            <br /><br />
            <ul>
                <li>{missing.length} record{missing.length !== 1 ? 's' : ''} added</li>
                <li>{modified.length} record{modified.length !== 1 ? 's' : ''} updated</li>
                <li>{deleted.length} record{deleted.length !== 1 ? 's' : ''} removed</li>
            </ul>
        </div>
    );

    const generateSuccessToasts = (data, domains) => {
        return Object.entries(data).map(([domainId, result]) => {
            const domain = domains.find(d => d['domain-id'] === Number(domainId));
            const domainName = domain['domain-name'];

            return {
                key: Math.random().toString(),
                intent: 'success',
                message: formatToastMessage(domainName, result.timestamp, result.missing, result.modified, result.deleted),
                ...domain,
                'last-sync-status': 'SUCCESS',
                'last-sync-attempt': result.timestamp,
            };
        });
    };

    const updateDomainStates = (domains, results, defaultStatus = 'SUCCESS') => {
        return domains.map(domain => {
            const result = results[String(domain['domain-id'])];
            if (result) {
                return {
                    ...domain,
                    'last-sync-status': defaultStatus,
                    'last-sync-attempt': result.timestamp,
                };
            }
            return domain;
        });
    };

    try {
        const { data } = await myAxios.post(
            `${this.props.baseUrl}/api/sync-dns-zone`,
            [...this.state.selectedDomains]
        );

        this.setState(prevState => {
            const updatedToasts = generateSuccessToasts(data, prevState.domains);
            const updatedDomains = updateDomainStates(prevState.domains, data);

            return {
                syncButtonState: '',
                domains: updatedDomains,
                toasts: [...prevState.toasts, ...updatedToasts],
            };
        });

    } catch (error) {
        const { message: errorMessage, domainId, timestamp, results = {} } = error;

        this.setState(prevState => {
            const failedDomain = prevState.domains.find(d => d['domain-id'] === domainId);
            const failedDomainName = failedDomain['domain-name'];

            const dangerToast = {
                key: Math.random().toString(),
                intent: 'danger',
                message: `Sync failed for ${failedDomainName} : ${errorMessage}`
            };

            const successfulToasts = generateSuccessToasts(results, prevState.domains);
            const updatedDomains = prevState.domains.map(domain => {
                if (domain['domain-id'] === domainId) {
                    return {
                        ...domain,
                        "last-sync-status": "FAILED",
                        "last-sync-attempt": timestamp
                    };
                }

                const result = results[String(domain['domain-id'])];
                if (result) {
                    return {
                        ...domain,
                        "last-sync-status": "SUCCESS",
                        "last-sync-attempt": result.timestamp
                    };
                }

                return domain;
            });

            return {
                domains: updatedDomains,
                syncButtonState: '',
                toasts: [...prevState.toasts, ...successfulToasts, dangerToast]
            };
        });
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
        console.error(error);
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
    console.log()
    this.setState({searchQuery: e.target.value});
};
