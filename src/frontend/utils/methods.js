import React from 'react';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { states } from './states';
import { saveAutoSyncStatus } from '../elements/Toolbar/utils'

export const handleToastClose = function (key) {
    this.setState(prevState => ({
        toasts: prevState.toasts.filter(item => item.key !== key)
    }));
};

export const handleSelectAll = function () {
    this.setState((prevState) => {
        const activeDomainIds = prevState.domains
            .filter(domain => domain["dns-status"] === true)
            .map(domain => domain["domain-id"]);

        return {
            selectedDomains: prevState.selectedDomains.size === activeDomainIds.length
                ? new Set()
                : new Set(activeDomainIds)
        };
    });
};

export const handleCheckboxChange = function (domainId) {
    this.setState((prevState) => {
        const updatedSelection = new Set(prevState.selectedDomains);
        updatedSelection.has(domainId) ? updatedSelection.delete(domainId) : updatedSelection.add(domainId);
        return {selectedDomains: updatedSelection};
    });
};

export const handleBulkAutoSync = function (status) {
    let bulkChanges = [];

    this.setState((prevState) => {
        const domains = [ ...prevState.domains ];

        prevState.selectedDomains.forEach(domainId => {
            const index = domains.findIndex(d => d["domain-id"] === domainId);
            const prevStatus = domains[index]["auto-sync-status"];

            if (index !== -1) {

                if (prevStatus !== status) {

                    domains[index] = {
                        ...domains[index],
                        ["auto-sync-status"]: status
                    };

                    bulkChanges.push([ domainId, status, prevStatus ]);
                }
            }
        });

        return { domains };
    }, () => {
        if (bulkChanges.length > 0) {
            saveAutoSyncStatus.call(this, bulkChanges);
        }
    });
};


export const handleSyncChange = function (domainId) {
    let domainData;

    this.setState(prevState => {
        const domains = [...prevState.domains];
        const index = domains.findIndex(d => d['domain-id'] === domainId);
        if (index !== -1) {
            const domain = { ...domains[index] }; // clone once
            const prevSyncStatus = domain['auto-sync-status'];
            const newStatus = prevSyncStatus === "true" ? "false" : "true";

            domain['auto-sync-status'] = newStatus;
            domains[index] = domain;

            domainData = [domainId, newStatus, prevSyncStatus];
        }
        return { domains };
    }, () => {
        if (domainData) saveAutoSyncStatus.call(this, domainData);
    });
};

export const revertAutoSyncStatus = function (domainChanges) {
    this.setState((prevState) => {
        const domains = [...prevState.domains];

        if (Array.isArray(domainChanges[0])) {
            domainChanges.forEach(([ domainId, _newStatus, prevStatus ]) => {
                const index = domains.findIndex(d => d["domain-id"] === domainId);

                if (index !== -1) {
                    domains[index] = {
                        ...domains[index],
                        ["auto-sync-status"]: prevStatus
                    };
                }
            });
        } else {
            const [ domainId, _newStatus, prevStatus ] = domainChanges;

            const index = domains.findIndex(d => d["domain-id"] === domainId);
            if (index !== -1) {
                domains[index] = {
                    ...domains[index],
                    ["auto-sync-status"]: prevStatus
                };
            }
        }

        return { domains };
    });
};




