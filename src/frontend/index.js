
import React from 'react';
import punycode from 'punycode/';
import { propTypes } from './utils/constants';
import { createElement, Component } from '@plesk/plesk-ext-sdk';
import { handleSortByChange, handleSortDirectionChange } from "./elements/SortingMenu/utils";
import { getDomainsInfo, getDomainRetentionStatus, saveDomainRetentionStatus, checkTokenExists, validateToken, getUserEulaDecision, saveUserEulaDecision} from './api-calls';
import DomainListToolbar from "elements/Toolbar/Toolbar";
import { states } from './utils/states';
import { handleAddDomainToDesec, handleDNSRecordsSync, handleSearchChange } from './elements/Toolbar/utils';
import { handleSelectAll, handleCheckboxChange, handleSyncChange, handleToastClose, handleBulkAutoSync } from "./utils/methods";

import {
    Link,
    Label,
    Switch,
    Tabs,
    Tab,
    SwitchesPanel,
    SwitchesPanelItem,
    Paragraph,
    Toaster,
    SkeletonText,
    List,
    ListEmptyView,
    Checkbox,
    Text,
    Section,
    FormFieldText,
    Dialog
} from '@plesk/ui-library';

export default class App extends Component {

    static propTypes = propTypes;
    state = states;

    componentDidMount = async () => {

        const eulaDecision = await getUserEulaDecision.call(this);
        const tokenStatus  = await checkTokenExists.call(this);

        this.setState({
            eulaDecision,
            tokenStatus,
        });

        if (tokenStatus && eulaDecision) {
            await getDomainsInfo.call(this);
            await getDomainRetentionStatus.call(this);
        }

        this.setState({ listLoading : false })


        const observer = window.Jsw.Observer;
        this._onPleskTaskComplete = (payload) => {

            const taskType = payload["type"];
            const additionalData = payload["additionalData"];

            if(taskType === "ext-ls-desec-dns-task_registerdomains") {
                this.setState({ addButtonState: ""})

                if(additionalData) {
                    this.setState(prevState => ({
                        domains: prevState.domains.map(domain => {

                            const taskResult = additionalData[domain["domain-id"]]
                            if(taskResult) {
                                return {
                                    ...domain,
                                    "desec-status": taskResult.status === "Registered" ? "Registered" : "Not Registered",
                                    "auto-sync-status": "true"
                                };
                            }

                            return domain;
                        })
                    }));
                }

            } else if (taskType === "ext-ls-desec-dns-task_syncdnszones") {
                this.setState({ syncButtonState: ""})

                if(additionalData) {
                    this.setState(prevState => ({
                        domains: prevState.domains.map(domain => {
                            const taskResult = additionalData[String(domain["domain-id"])]

                            if (taskResult && taskResult?.code) {
                                return {
                                    ...domain,
                                    "desec-status": "Not Registered",
                                    "last-sync-status": "No data",
                                    "last-sync-attempt": "No date"
                                };
                            } else if(taskResult) {
                                return {
                                    ...domain,
                                    "last-sync-status": taskResult['last_sync_status'],
                                    "last-sync-attempt": taskResult['timestamp']
                                };
                            }

                            return domain;
                        })
                    }));
                }
            }
        };


        observer.addEventListener('plesk:taskComplete', this._onPleskTaskComplete);
    };


    getFilteredSortedDomains() {
        const { domains, searchQuery, sortingBy, sortingDirection } = this.state;

        const hasChanged =
            this._memoCache === undefined ||
            this._memoCache.domains !== domains ||
            this._memoCache.searchQuery !== searchQuery ||
            this._memoCache.sortingBy !== sortingBy ||
            this._memoCache.sortingDirection !== sortingDirection;

        if (!hasChanged) {
            return this._memoCache.result;
        }

        const filtered = domains.filter(d =>
            d['domain-name']?.toLowerCase().includes(searchQuery.trim().toLowerCase())
        );

        const sorted = [...filtered].sort((a, b) => {
            const getValue = (domain, key) => domain[key]?.toLowerCase() ?? '';
            const aVal = getValue(a, sortingBy);
            const bVal = getValue(b, sortingBy);

            if (aVal < bVal) return sortingDirection === 'ASC' ? -1 : 1;
            if (aVal > bVal) return sortingDirection === 'ASC' ? 1 : -1;
            return 0;
        });

        this._memoCache = {
            domains,
            searchQuery,
            sortingBy,
            sortingDirection,
            result: sorted
        };

        return sorted;
    }


    renderColumns(allSelected, isIndeterminate, selectedDomains) {
        return [
            {
                key: 'checkbox',
                title: <Checkbox checked={allSelected} indeterminate={isIndeterminate} onChange={handleSelectAll.bind(this)} />,
                width: '5%',
                render: row => (
                    <Checkbox
                        checked={selectedDomains.has(row["domain-id"])}
                        onChange={() => handleCheckboxChange.call(this, row["domain-id"])}
                        disabled={!row["dns-status"]}
                    />
                )
            },
            {
                key: 'domain-name',
                title: 'Domain',
                render: row => {
                    const asciiDomain = row['domain-name']
                        .split('.')
                        .map(part => punycode.toUnicode(part))
                        .join('.');

                    return (
                        <Link
                            href={row['domain-link']}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {asciiDomain}
                        </Link>
                    );
                }
            },

            {
                key: 'last-sync-status',
                title: 'Last Sync Status',
                render: row => {
                    const status = row["last-sync-status"];
                    if (status.startsWith("SUCCESS")) return <Label icon="check-mark-circle-filled" size="sm" view="light" intent="success">{status}</Label>;
                    if (status.startsWith("FAILED")) return <Label icon="exclamation-mark-circle" size="sm" view="light" intent="danger">{status}</Label>;
                    return <Text intent="muted">No data</Text>;
                }
            },
            {
                key: 'last-sync-attempt',
                title: 'Last Sync Attempt',
                render: row => <Text intent={row['last-sync-attempt'] === "No date" ? "muted" : undefined}>{row['last-sync-attempt']}</Text>
            },
            {
                key: 'dns-status',
                title: "Plesk DNS Status",
                render: row => row['dns-status'] ? <Label intent="success">Active</Label> : <Label intent="warning">Disabled</Label>

            },

            {
                key: 'domain-desec-status',
                title: "deSEC Status",
                render: row => {
                    if(row['desec-status'] === "Registered") {
                        return (<Label intent="success">Registered</Label>)
                    } else if(row['desec-status'] === "Error") {
                        return (<Label intent="danger">Error</Label>)
                    } else {
                        return (<Label intent="warning">Not Registered</Label>)
                    }
                }
            },

            {
                key: 'auto-sync-desec',
                title: 'Auto-Sync',
                render: row => {
                    const domainId = row["domain-id"];
                    const enabled = row["auto-sync-status"] === "true" && row["desec-status"] === "Registered";
                    const disabled = !row["dns-status"] || row["desec-status"] === "Not Registered" || row["desec-status"] === "Error";
                    return (
                        <div style={{ display: 'flex', alignItems: 'center' }}>
                            <Switch checked={enabled} onChange={() => handleSyncChange.call(this, domainId)} disabled={disabled} />
                            <span style={{ marginLeft: 10 }}>{enabled ? 'Enabled' : 'Disabled'}</span>
                        </div>
                    );
                }
            }
        ];
    }

    render() {

        const {
            domains,
            selectedDomains,
            retainDomainCheck,
            toasts,
            listLoading,
            sortingBy,
            sortingDirection,
            addButtonState,
            syncButtonState,
            searchQuery,
            tokenStatus,
            emptyViewTitle,
            emptyViewDescription,
            eulaDecision,
        } = this.state;

        const filtered = domains.filter(d => d['domain-name']?.toLowerCase().includes(searchQuery.trim().toLowerCase()));
        const allSelected = filtered.length > 0 && selectedDomains.size === filtered.length;
        const isIndeterminate = selectedDomains.size > 0 && selectedDomains.size < filtered.length;
        const selectedDomainObjects = domains.filter(d => selectedDomains.has(d['domain-id']));
        const allSelectedAreRegistered = selectedDomainObjects.every(d => d['desec-status'] === 'Registered');
        const allSelectedAreNotRegistered = selectedDomainObjects.every(d => d['desec-status'] === 'Not Registered' || d['desec-status'] === 'Error');
        const sorted = this.getFilteredSortedDomains();
        const columns = this.renderColumns(allSelected, isIndeterminate, selectedDomains);

        const needsEula = !eulaDecision
        const needsToken = !tokenStatus
        
        return (
            <div>
                {needsEula || needsToken ? (
                    <Dialog
                        isOpen={needsEula || needsToken}
                        title={needsEula ? "Privacy Policy & deSEC token" : "deSEC Credentials"}
                        size="sm"
                        closingConfirmation={true}
                        // banner={<img src="img/dialog-banner.png" alt="" />}

                        onClose={async () => {
                            await saveUserEulaDecision.call(this, false);

                            this.setState({
                                eulaDecision: false,
                                tokenStatus: false,
                                emptyViewTitle: "License agreement & deSEC token pop-up dialog was closed!",
                                emptyViewDescription:
                                    "Acceptance of the license agreement and deSEC token creation are required to use this extension. To revisit the pop-up dialog, please refresh the page.",
                                listLoading: false,
                            });

                        }}


                        form={{
                            onSubmit: async () => {

                                try {
                                    if (needsEula) await saveUserEulaDecision.call(this, true);
                                    if (needsToken) await validateToken.call(this);
                                } finally {
                                    this.setState({ eulaDecision: true }, async () => {
                                        if (this.state.tokenStatus) {
                                            await getDomainsInfo.call(this);
                                        }
                                    });
                                }

                            },

                            submitButton: { children: needsToken && !needsEula ? "Submit" : "Agree!" },
                            cancelButton: { children: needsToken && !needsEula ? "Cancel" : "I do not agree!" },
                        }}
                    >
                        {needsToken ? (
                         <div>
                             <Section title="deSEC API Token" >
                                 <FormFieldText
                                     label="API Token"
                                     size="lg"
                                     required
                                     onChange={(value) => this.setState({ inputToken: value })}
                                 />
                             </Section>
                         </div>
                        ) : null}

                        {needsEula ? (
                          <div>
                              <Section title="License Agreement">
                                  <Paragraph>
                                      <Link href="https://raw.githubusercontent.com/layershift/ext-ls-desec/refs/heads/main/LICENSE" target="_blank" rel="noopener noreferrer">
                                          Open License
                                      </Link>
                                  </Paragraph>
                              </Section>

                              <Paragraph>
                                  By pressing the "Agree!" button, you accept the extension's license agreement!
                              </Paragraph>
                          </div>
                        ) : null}

                    </Dialog>
                ) : null}

                <Tabs active={1} monospaced>
                    <Tab key={1} title="Control Panel" icon="cd-up-in-cloud">
                        {listLoading ? (
                            <SkeletonText lines={10} />
                        ) : (
                            <>
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
                                    enableBulkAutoSync={handleBulkAutoSync.bind(this, "true")}
                                    disableBulkAutoSync={handleBulkAutoSync.bind(this, "false")}
                                    handleSearchChange={handleSearchChange.bind(this)}
                                    handleSortByChange={handleSortByChange.bind(this)}
                                    handleSortDirectionChange={handleSortDirectionChange.bind(this)}
                                />
                                <List
                                    columns={columns}
                                    data={sorted}
                                    rowKey={row => row['domain-id']}
                                    sortColumn={{ sortBy: sortingBy, direction: sortingDirection }}
                                    emptyView={<ListEmptyView title={emptyViewTitle} description={emptyViewDescription} />}
                                />
                            </>
                        )}
                    </Tab>
                    <Tab key={2} title="Settings" icon="gear">
                        <SwitchesPanel title="Settings">
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                                <SwitchesPanelItem
                                    switchProps={{ checked: retainDomainCheck === "true" }}
                                    onChange={saveDomainRetentionStatus.bind(this)}
                                    title="Domain retention in deSEC"
                                    description="Keep your DNS zone active in deSEC even if the domain is removed from Plesk."
                                    fullDescription={<Paragraph>This option's state will reflect whether or not you prefer that a domain, if deleted from Plesk, to be retained (along with its DNS zone) in deSEC.</Paragraph>}
                                    style={{ width: 400 }}
                                />
                            </div>
                        </SwitchesPanel>
                    </Tab>
                </Tabs>
                <Toaster toasts={toasts} onToastClose={handleToastClose.bind(this)} />
            </div>
        );
    }
}