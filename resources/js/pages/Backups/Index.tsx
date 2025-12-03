import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { DatabaseConnection } from '@/types/database-connection';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Database, Download, Filter, HardDrive, RefreshCw, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

interface Backup {
    id: number;
    connection_id: number;
    connection_name: string;
    connection_host: string;
    connection_database: string;
    filename: string;
    size: string | null;
    path: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    progress: number;
    log: string | null;
    created_at: string;
    download_url: string | null;
    delete_url: string;
    restore_url: string | null;
}

interface Props {
    backups: Backup[];
    connections: DatabaseConnection[];
    backupDisks: BackupDisk[];
}

interface BackupDisk {
    id: number;
    name: string;
    driver: string;
    is_default: boolean;
}

export default function Index({ backups: initialBackups, connections, backupDisks }: Props) {
    const [backups, setBackups] = useState<Backup[]>(initialBackups);
    const [searchQuery, setSearchQuery] = useState('');
    const [connectionFilter, setConnectionFilter] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; backup: Backup | null }>({ open: false, backup: null });
    const [restoreDialog, setRestoreDialog] = useState<{
        open: boolean;
        backup: Backup | null;
        loading: boolean;
        targetConnectionId: string;
    }>({
        open: false,
        backup: null,
        loading: false,
        targetConnectionId: '',
    });

    const filteredBackups = useMemo(() => {
        return backups.filter((backup) => {
            const matchesSearch = backup.filename.toLowerCase().includes(searchQuery.toLowerCase()) ||
                backup.connection_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                backup.connection_database.toLowerCase().includes(searchQuery.toLowerCase());

            const matchesConnection = connectionFilter === 'all' ||
                backup.connection_id.toString() === connectionFilter;

            const matchesStatus = statusFilter === 'all' || backup.status === statusFilter;

            return matchesSearch && matchesConnection && matchesStatus;
        });
    }, [backups, searchQuery, connectionFilter, statusFilter]);

    const confirmDelete = (backup: Backup) => {
        setDeleteDialog({ open: true, backup });
    };

    const handleDelete = () => {
        if (!deleteDialog.backup) return;

        router.delete(deleteDialog.backup.delete_url, {
            onSuccess: () => {
                toast.success('Backup deleted');
                setBackups((prev) => prev.filter((b) => b.id !== deleteDialog.backup!.id));
                setDeleteDialog({ open: false, backup: null });
            },
            onError: () => {
                toast.error('Failed to delete backup');
                setDeleteDialog({ open: false, backup: null });
            },
        });
    };

    const confirmRestore = (backup: Backup) => {
        setRestoreDialog({
            open: true,
            backup,
            loading: false,
            targetConnectionId: backup.connection_id.toString(),
        });
    };

    const handleRestore = () => {
        if (!restoreDialog.backup || !restoreDialog.targetConnectionId) return;

        const targetConnection = connections.find((c) => c.id.toString() === restoreDialog.targetConnectionId);
        if (!targetConnection) {
            toast.error('Please select a target connection');
            return;
        }

        setRestoreDialog((prev) => ({ ...prev, loading: true }));

        const restoreToastId = toast.loading(`Starting restore to ${targetConnection.name}...`, {
            duration: Infinity,
        });

        // Use axios.post with CSRF token configured in app.tsx
        axios.post(restoreDialog.backup.restore_url!, {
            target_connection_id: restoreDialog.targetConnectionId,
        })
        .then((response) => {
            // Store the restore ID from the response
            const restoreId = response.data?.restore_id;

            if (!restoreId) {
                toast.error('Failed to get restore ID', {
                    id: restoreToastId,
                });
                setRestoreDialog({ open: false, backup: null, loading: false, targetConnectionId: '' });
                return;
            }

            toast.loading(`Restore in progress to ${targetConnection.name}...`, {
                id: restoreToastId,
                duration: Infinity,
            });
            setRestoreDialog({ open: false, backup: null, loading: false, targetConnectionId: '' });

            const pollInterval = setInterval(() => {
                // Poll the restore status
                axios.get(`/restores/${restoreId}/status`)
                    .then((response) => {
                        const { status, progress, log } = response.data;

                        // Update the notification with progress
                        if (status === 'processing' || status === 'pending') {
                            toast.loading(`Restore in progress to ${targetConnection.name}... (${progress}%)`, {
                                id: restoreToastId,
                                duration: Infinity,
                            });
                        }

                        // Check if restore is completed
                        if (status === 'completed') {
                            clearInterval(pollInterval);
                            toast.success(`Restore completed successfully to ${targetConnection.name}`, {
                                id: restoreToastId,
                            });
                        } else if (status === 'failed') {
                            clearInterval(pollInterval);
                            toast.error(`Restore failed on ${targetConnection.name}${log ? ': ' + log : ''}`, {
                                id: restoreToastId,
                            });
                        }
                    })
                    .catch(() => {
                        // Silently fail on polling errors
                    });
            }, 2000);

            setTimeout(() => {
                clearInterval(pollInterval);
            }, 600000);
        })
        .catch((error) => {
            toast.error(error.response?.data?.message || 'Failed to start restore', {
                id: restoreToastId,
            });
            setRestoreDialog({ open: false, backup: null, loading: false, targetConnectionId: '' });
        });
    };

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'completed':
                return 'default';
            case 'failed':
                return 'destructive';
            case 'processing':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Backups', href: '/backups' },
            ]}
        >
            <Head title="All Backups" />

            <div className="p-6 max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">All Backups</h1>
                        <p className="text-muted-foreground mt-1">
                            Manage and restore backups from all database connections
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
                            <div className="flex-1 w-full md:max-w-sm">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                    <Input
                                        placeholder="Search backups..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            <div className="flex gap-2 w-full md:w-auto">
                                <Select value={connectionFilter} onValueChange={setConnectionFilter}>
                                    <SelectTrigger className="w-full md:w-[200px]">
                                        <div className="flex items-center gap-2">
                                            <Database className="h-4 w-4" />
                                            <SelectValue placeholder="Filter by connection" />
                                        </div>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Connections</SelectItem>
                                        {connections.map((conn) => (
                                            <SelectItem key={conn.id} value={conn.id.toString()}>
                                                {conn.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger className="w-full md:w-[150px]">
                                        <div className="flex items-center gap-2">
                                            <Filter className="h-4 w-4" />
                                            <SelectValue placeholder="Filter by status" />
                                        </div>
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                        <SelectItem value="processing">Processing</SelectItem>
                                        <SelectItem value="pending">Pending</SelectItem>
                                        <SelectItem value="failed">Failed</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Backup File</TableHead>
                                    <TableHead>Source Connection</TableHead>
                                    <TableHead>Size</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredBackups.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground h-32">
                                            No backups found. Create your first backup to get started.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    filteredBackups.map((backup) => (
                                        <TableRow key={backup.id}>
                                            <TableCell className="font-mono text-sm">
                                                {backup.filename || 'Pending...'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-medium">{backup.connection_name}</span>
                                                    <span className="text-sm text-muted-foreground">
                                                        {backup.connection_host}/{backup.connection_database}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>{backup.size || '-'}</TableCell>
                                            <TableCell>
                                                {backup.status === 'processing' ? (
                                                    <div className="space-y-2 min-w-[150px]">
                                                        <div className="flex items-center justify-between">
                                                            <Badge variant="secondary">Processing</Badge>
                                                            <span className="text-sm font-medium">{backup.progress}%</span>
                                                        </div>
                                                        <Progress value={backup.progress} className="h-2" />
                                                    </div>
                                                ) : (
                                                    <div className="space-y-1">
                                                        <Badge variant={getStatusVariant(backup.status)}>
                                                            {backup.status}
                                                        </Badge>
                                                        {backup.status === 'failed' && backup.log && (
                                                            <p
                                                                className="text-xs text-destructive max-w-[200px] truncate"
                                                                title={backup.log}
                                                            >
                                                                {backup.log}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {backup.created_at}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {backup.status === 'completed' && (
                                                        <>
                                                            <Button variant="outline" size="sm" asChild>
                                                                <a href={backup.download_url!} target="_blank" rel="noreferrer">
                                                                    <Download className="h-4 w-4 mr-1" />
                                                                    Download
                                                                </a>
                                                            </Button>
                                                            <Button
                                                                variant="secondary"
                                                                size="sm"
                                                                onClick={() => confirmRestore(backup)}
                                                            >
                                                                <RefreshCw className="h-4 w-4 mr-1" />
                                                                Restore
                                                            </Button>
                                                        </>
                                                    )}
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => confirmDelete(backup)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                        {filteredBackups.length > 0 && (
                            <div className="mt-4 text-sm text-muted-foreground">
                                Showing {filteredBackups.length} of {backups.length} backups
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <AlertDialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog({ open, backup: null })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Backup</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this backup? This action cannot be undone.
                            <br />
                            <span className="font-mono text-sm mt-2 block">
                                {deleteDialog.backup?.filename}
                            </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={restoreDialog.open}
                onOpenChange={(open) => setRestoreDialog({ open: false, backup: null, loading: false, targetConnectionId: '' })}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Restore Database</AlertDialogTitle>
                        <AlertDialogDescription>
                            Select the target database connection to restore this backup to.
                            <br />
                            <span className="font-mono text-sm mt-2 block font-semibold text-foreground">
                                {restoreDialog.backup?.filename}
                            </span>
                            <span className="text-sm text-muted-foreground mt-1 block">
                                From: {restoreDialog.backup?.connection_name} ({restoreDialog.backup?.connection_host}/{restoreDialog.backup?.connection_database})
                            </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="py-4">
                        <label className="text-sm font-medium mb-2 block">Target Connection</label>
                        <Select
                            value={restoreDialog.targetConnectionId}
                            onValueChange={(value) =>
                                setRestoreDialog((prev) => ({ ...prev, targetConnectionId: value }))
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select a connection" />
                            </SelectTrigger>
                            <SelectContent>
                                {connections.map((conn) => (
                                    <SelectItem key={conn.id} value={conn.id.toString()}>
                                        <div className="flex items-center gap-2">
                                            <Database className="h-4 w-4" />
                                            <span>{conn.name}</span>
                                            <span className="text-sm text-muted-foreground">
                                                ({conn.username}@{conn.host}:{conn.port}/{conn.database})
                                            </span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-sm text-muted-foreground mt-2">
                            This will overwrite the database at the selected connection.
                        </p>
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={restoreDialog.loading}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleRestore}
                            disabled={!restoreDialog.targetConnectionId || restoreDialog.loading}
                        >
                            {restoreDialog.loading ? 'Starting...' : 'Restore'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
