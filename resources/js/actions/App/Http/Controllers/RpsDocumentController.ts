import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\RpsDocumentController::updateMeta
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
export const updateMeta = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateMeta.url(args, options),
    method: 'put',
})

updateMeta.definition = {
    methods: ["put"],
    url: '/rps/{rps}/document-meta',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsDocumentController::updateMeta
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
updateMeta.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return updateMeta.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDocumentController::updateMeta
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
updateMeta.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateMeta.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsDocumentController::updateMeta
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
    const updateMetaForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateMeta.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDocumentController::updateMeta
 * @see app/Http/Controllers/RpsDocumentController.php:16
 * @route '/rps/{rps}/document-meta'
 */
        updateMetaForm.put = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateMeta.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateMeta.form = updateMetaForm
/**
* @see \App\Http\Controllers\RpsDocumentController::generateAiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
export const generateAiReferences = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateAiReferences.url(args, options),
    method: 'post',
})

generateAiReferences.definition = {
    methods: ["post"],
    url: '/rps/{rps}/document-meta/ai-references',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsDocumentController::generateAiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
generateAiReferences.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return generateAiReferences.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDocumentController::generateAiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
generateAiReferences.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: generateAiReferences.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsDocumentController::generateAiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
    const generateAiReferencesForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: generateAiReferences.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDocumentController::generateAiReferences
 * @see app/Http/Controllers/RpsDocumentController.php:73
 * @route '/rps/{rps}/document-meta/ai-references'
 */
        generateAiReferencesForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: generateAiReferences.url(args, options),
            method: 'post',
        })
    
    generateAiReferences.form = generateAiReferencesForm
/**
* @see \App\Http\Controllers\RpsDocumentController::updateSimulationScore
 * @see app/Http/Controllers/RpsDocumentController.php:251
 * @route '/rps/{rps}/simulation/{week}'
 */
export const updateSimulationScore = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSimulationScore.url(args, options),
    method: 'put',
})

updateSimulationScore.definition = {
    methods: ["put"],
    url: '/rps/{rps}/simulation/{week}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsDocumentController::updateSimulationScore
 * @see app/Http/Controllers/RpsDocumentController.php:251
 * @route '/rps/{rps}/simulation/{week}'
 */
updateSimulationScore.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    week: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                week: args.week,
                }

    return updateSimulationScore.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDocumentController::updateSimulationScore
 * @see app/Http/Controllers/RpsDocumentController.php:251
 * @route '/rps/{rps}/simulation/{week}'
 */
updateSimulationScore.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateSimulationScore.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsDocumentController::updateSimulationScore
 * @see app/Http/Controllers/RpsDocumentController.php:251
 * @route '/rps/{rps}/simulation/{week}'
 */
    const updateSimulationScoreForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateSimulationScore.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDocumentController::updateSimulationScore
 * @see app/Http/Controllers/RpsDocumentController.php:251
 * @route '/rps/{rps}/simulation/{week}'
 */
        updateSimulationScoreForm.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateSimulationScore.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateSimulationScore.form = updateSimulationScoreForm
/**
* @see \App\Http\Controllers\RpsDocumentController::updateWeekWeight
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
export const updateWeekWeight = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateWeekWeight.url(args, options),
    method: 'put',
})

updateWeekWeight.definition = {
    methods: ["put"],
    url: '/rps/{rps}/weeks/{week}/weight',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\RpsDocumentController::updateWeekWeight
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
updateWeekWeight.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    week: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                week: args.week,
                }

    return updateWeekWeight.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsDocumentController::updateWeekWeight
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
updateWeekWeight.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: updateWeekWeight.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\RpsDocumentController::updateWeekWeight
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
    const updateWeekWeightForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: updateWeekWeight.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsDocumentController::updateWeekWeight
 * @see app/Http/Controllers/RpsDocumentController.php:158
 * @route '/rps/{rps}/weeks/{week}/weight'
 */
        updateWeekWeightForm.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: updateWeekWeight.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    updateWeekWeight.form = updateWeekWeightForm
const RpsDocumentController = { updateMeta, generateAiReferences, updateSimulationScore, updateWeekWeight }

export default RpsDocumentController