import React from 'react';
import { propTypes } from './utils/constants';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import {  handleSortByChange, handleSortDirectionChange } from "./elements/SortingMenu/utils"
import { getDomainsInfo, getDomainRetentionStatus, saveDomainRetentionStatus, getLogVerbosityStatus, saveLogVerbosityStatus } from './api-calls'
import { Link, Label, Switch, Tabs, Tab, SwitchesPanel, SwitchesPanelItem, Paragraph, Toaster, SkeletonText, List, ListEmptyView, Checkbox, Text } from '@plesk/ui-library';
import DomainListToolbar from "elements/Toolbar/Toolbar";
import { states } from './utils/states';
import { handleAddDomainToDesec, handleDNSRecordsSync, handleSearchChange, isSelectedDomainRegistered } from './elements/Toolbar/utils'
import { handleSelectAll, handleCheckboxChange, handleSyncChange, handleToastClose, handleBulkAutoSync } from "./utils/methods";

export default class App extends Component {
    static propTypes = propTypes;
    state = states;

    componentDidMount() {
        getDomainsInfo.call(this)

            .then(r => {
                getDomainRetentionStatus.call(this);
                getLogVerbosityStatus.call(this);
            })
            .finally(() => {
                this.setState({ listLoading: false })
            });

    }
    render() {

        const { domains, retainDomainCheck,toasts, listLoading, sortingBy, sortingDirection, selectedDomains, addButtonState, syncButtonState, searchQuery, lastSyncTimestampsSuccess, logVerbosityStatus } = this.state;

        console.log(selectedDomains);
        const filtered = domains.filter(d => d['domain-name']?.toLowerCase().includes(searchQuery.trim().toLowerCase()));
        const allSelected = filtered.length > 0 && selectedDomains.size === filtered.length;
        const isIndeterminate = selectedDomains.size > 0 && selectedDomains.size < filtered.length;
        const selectedDomainObjects = domains.filter(d => selectedDomains.has(d['domain-id']));
        const allSelectedAreRegistered = selectedDomainObjects.every(d => d['desec-status'] === 'Registered');
        const allSelectedAreNotRegistered = selectedDomainObjects.every(d => d['desec-status'] === 'Not Registered');


        const sorted = [...filtered].sort((a, b) => {
            let aVal;
            let bVal;
            if (sortingBy === 'domain-name') {
                aVal = a['domain-name'].toLowerCase();
                bVal = b['domain-name'].toLowerCase();
            } else {
                aVal = (lastSyncTimestampsSuccess[a['domain-id']]?.status || '').toLowerCase();
                bVal = (lastSyncTimestampsSuccess[b['domain-id']]?.status || '').toLowerCase();
            }
            if (aVal < bVal) return sortingDirection === 'ASC' ? -1 : 1;
            if (aVal > bVal) return sortingDirection === 'ASC' ? 1 : -1;
            return 0;
        });


        const columns = [
            {
                key: 'checkbox',
                title: <Checkbox checked={allSelected} indeterminate={isIndeterminate}
                                 onChange={handleSelectAll.bind(this)}/>,
                width: '5%',
                render: row => (
                    <Checkbox checked={selectedDomains.has(row["domain-id"])}
                              onChange={() => handleCheckboxChange.call(this, row["domain-id"])}
                              disabled={!row["dns-status"]}/>
                ),
            },
            {
                key: 'domain-name',
                title: 'Domain',
                render: row => <Link to={`#${row['domain-name']}`}>{row['domain-name']}</Link>,
            },
            {
                key: 'last-sync-status',
                title: 'Last Sync Status',
                render: row => {
                    const lastSyncStatus = row["last-sync-status"];

                    return (
                        <>
                            {lastSyncStatus === "SUCCESS" ? (
                                <Label
                                    icon="check-mark-circle-filled"
                                    size="sm"
                                    view="light"
                                    intent="success"
                                >
                                    Success
                                </Label>
                            ) : lastSyncStatus === "FAILED" ? (
                                <Label
                                    icon="exclamation-mark-circle"
                                    size="sm"
                                    view="light"
                                    intent="danger"
                                >
                                    Failed
                                </Label>
                            ) : (
                                <Text intent="muted">No data</Text>
                            )}
                        </>
                    )
                }
            },
            {
                key: 'last-sync-attempt',
                title: 'Last Sync Attempt',
                render: row => {
                    const ts = row['last-sync-attempt']
                    return (
                        <>
                            {ts !== "No date" ? (
                                <Text>{ts}</Text>
                            ) : (
                                <Text intent="muted">{ts}</Text>
                            )}
                        </>
                    )
                }
            },
            {
                key: 'dns-status',
                title: "Plesk DNS Status",
                render: row => {
                    const st = row['dns-status'];
                    return st === true
                        ? <Label intent="success">Active</Label>
                        : <Label intent="warning">Disabled</Label>
                }
            },
            {
                key: 'domain-desec-status',
                title: "deSEC Status",
                render: row => {
                    const found = row['desec-status'];
                    return found === "Registered"
                        ? <Label intent="success">Registered</Label>
                        : <Label intent="warning">Not Registered</Label>;
                }
            },
            {
                key: 'auto-sync-desec',
                title: 'Auto-Sync',
                render: row => {
                    const domainId = row["domain-id"];
                    const enabled = row["auto-sync-status"] === "true" && row["desec-status"] === "Registered";
                    const disabled = !row["dns-status"] || row["desec-status"] === "Not Registered";
                    return (
                        <div style={{ display: 'flex', alignItems: 'center' }}>
                            <Switch checked={enabled} onChange={() => handleSyncChange.call(this, domainId)} disabled={disabled} />
                            <span style={{ marginLeft: 10 }}>{enabled ? 'Enabled' : 'Disabled'}</span>
                        </div>
                    );
                }
            }
        ];

        return (
            <div>
                <Tabs active={1} monospaced>
                    <Tab key={1} title="Control Panel" icon="cd-up-in-cloud">
                        {listLoading
                            ? <SkeletonText lines={5} />
                            : (
                                <List
                                    columns={columns}
                                    data={sorted}
                                    key={this.state.domains.map(d => d['domain-id'] + d['auto-sync-status']).join(',')}
                                    rowKey={row => row['domain-id']}
                                    sortColumn={{ sortBy: sortingBy, direction: sortingDirection }}

                                    toolbar={
                                        <DomainListToolbar
                                            selectedDomains={selectedDomains}
                                            sortingBy={sortingBy}
                                            sortingDirection={sortingDirection}
                                            addButtonState={addButtonState}
                                            syncButtonState={syncButtonState}
                                            searchQuery={searchQuery}
                                            allSelectedAreRegistered={allSelectedAreRegistered}
                                            allSelectedAreNotRegistered={allSelectedAreNotRegistered}
                                            handleAddDomainToDesec={handleAddDomainToDesec.bind(this)}
                                            handleDNSRecordsSync={handleDNSRecordsSync.bind(this)}
                                            enableBulkAutoSync={handleBulkAutoSync.bind(this, true)}
                                            disableBulkAutoSync={handleBulkAutoSync.bind(this, false)}
                                            handleSearchChange={handleSearchChange.bind(this)}
                                            handleSortByChange={handleSortByChange.bind(this)}
                                            handleSortDirectionChange={handleSortDirectionChange.bind(this)}
                                        />
                                    }

                                    emptyView={<ListEmptyView title="No domains!" description="Add domains in Plesk to sync with deSEC." />}
                                />
                            )}
                    </Tab>

                    <Tab key={2} title="Settings" icon="gear">
                        <SwitchesPanel title="Settings">
                            <br/>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                                <SwitchesPanelItem
                                    switchProps={{ checked: retainDomainCheck === "true" }}
                                    onChange={saveDomainRetentionStatus.bind(this)}
                                    title="Domain retention in deSEC"
                                    description="Keep your DNS zone active in deSEC even if the domain is removed from Plesk."
                                    fullDescription={
                                        <Paragraph>
                                            This option's state will reflect whether or not you prefer that a domain,
                                            if deleted from Plesk, to be retained (along with its DNS zone) in deSEC.
                                        </Paragraph>
                                    }
                                    style={{ width: 400 }}
                                />

                                <SwitchesPanelItem
                                    switchProps={{ checked: logVerbosityStatus === "true" }}
                                    onChange={saveLogVerbosityStatus.bind(this)}
                                    title="Log Verbosity"
                                    description="Log verbosity controls how much of your actions within the extension are getting logged."
                                    fullDescription={
                                        <Paragraph>
                                            Log verbosity refers to the level of detail included in log messages. Higher verbosity levels
                                            provide more detailed information for debugging, while lower levels show only critical or essential events.
                                        </Paragraph>
                                    }
                                    style={{ width: 400 }}
                                />
                            </div>

                        </SwitchesPanel>
                    </Tab>
                </Tabs>
                <Toaster toasts={toasts} onToastClose={handleToastClose.bind(this)} />
            </div>
        )
    }

}